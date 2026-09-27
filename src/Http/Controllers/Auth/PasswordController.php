<?php
/**
 * PasswordController class file
 *
 * PHP Version 8.1
 *
 * @category Controller
 * @package  Rivet\Http\Controllers\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Rivet\Data\Models\Auth\User;
use Rivet\Data\Models\Token;
use Rivet\Http\Controllers\BaseController;
use Rivet\Mail\BaseMail;
use Rivet\Support\FrontendUrl;

/**
 * PasswordController
 *
 * @category Controller
 * @package  Rivet\Http\Controllers\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class PasswordController extends BaseController
{
    /**
     * Method called by the /api/auth/pwd/forgot URL in POST.
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function forgot(Request $request): JsonResponse
    {
        $user_model = config('crud.user_model');

        if (config('auth.login_case_sensitive')) {
            $user = $user_model::firstWhere('login', $request->get('login'));
        } else {
            $user = $user_model::firstWhere(DB::raw('LOWER(login)'), Str::lower($request->get('login')));
        }

        if (!is_null($user) && !is_null($user->email) && $user->is_active) {
            // Revokes any previous reset link: only the last one works.
            [ $token_string, $expires_at ] = $user->issuePasswordToken(
                Token::PURPOSE_PWD_FORGOT
            );

            // Même route front que la création de mot de passe (même
            // process, voir docs internes) — même helper, résolu ici
            // de façon synchrone pour la même raison que dans UserTrait.
            Mail::send(new BaseMail('rivet::emails.auth.forgot', [
                'user'             => $user,
                'token'            => $token_string,
                'token_expires_at' => $expires_at,
                'url'              => FrontendUrl::build('password', $token_string),
                'subject'          => trans('rivet::mail.subject_auth_forgot')
            ]));
        }

        // Same answer whether the login exists or not (no enumeration).
        $this->setResponse(trans('rivet::pwd.email'));

        return $this->response->format();
    }

    /**
     * Method called by the /api/auth/pwd/{token} URL in POST.
     *
     * @param string  $token   The plain token
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function mailRenew(string $token, Request $request): JsonResponse
    {
        // Unknown, expired, or not a password token: same clean error.
        $record = Token::findValid($token, Token::PASSWORD_PURPOSES);
        $user = $record?->tokenable;

        $this->setResponse(trans('rivet::pwd.token'), 500);

        if ($user instanceof User && is_null($user->deleted_at)) {
            $user->password = $request->get('password');

            if ($user->save()) {
                // Single use: this link and any other pending one die here.
                $user->revokePasswordTokens();

                if (config('auth.pwd_reset_revokes_sessions')) {
                    $user->tokens()->delete();
                }

                $this->setResponse(trans('rivet::pwd.renew'), 200);
            }
        }

        return $this->response->format();
    }

    /**
     * Method called by the /api/auth/pwd/renew URL in POST.
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function renew(Request $request): JsonResponse
    {
        $this->setResponse(trans('rivet::pwd.error'), 500);

        $request->user()->password = $request->get('new_password');

        if ($request->user()->save()) {
            $request->user()->revokePasswordTokens();

            $this->setResponse(trans('rivet::pwd.renew'), 200);
        }

        return $this->response->format();
    }
}
