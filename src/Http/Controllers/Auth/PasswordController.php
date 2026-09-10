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

use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Rivet\Data\Models\Token;
use Rivet\Http\Controllers\BaseController;
use Rivet\Mail\BaseMail;

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
            $user = $user_model::firstWhere('login', $request->login);
        } else {
            $user = $user_model::firstWhere(DB::raw('LOWER(login)'), Str::lower($request->get('login')));
        }

        $this->setResponse(trans('rivet::pwd.error'), 500);

        if (!is_null($user) && !is_null($user->email)) {
            $token_string = Token::generateTokenString();
            $duration_min = config('auth.pwd_token_validity');
            $creation_date = new DateTime();

            $user->pwdTokens()->create([
                'purpose'    => 'pwd_forgot',
                'name'       => "pwd_forgot-{$creation_date->getTimestamp()}",
                'token'      => $token_string,
                'expires_at' => $creation_date->modify("+{$duration_min} minutes")
            ]);

            Mail::send(new BaseMail('rivet::emails.auth.forgot', [
                'user'             => $user,
                'token'            => $token_string,
                'token_expires_at' => $creation_date,
                'subject'          => trans('rivet::mail.subject_auth_forgot')
            ]));

            $this->setResponse(trans('rivet::pwd.email'));
        }

        return $this->response->format();
    }

    /**
     * Method called by the /api/auth/pwd/{token} URL in POST.
     *
     * @param string  $token   The valid token
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function mailRenew(string $token, Request $request): JsonResponse
    {
        $token = Token::firstWhere('token', hash('sha256', $token));

        $this->setResponse(trans('rivet::pwd.token'), 500);

        // The is_null($token) check MUST happen before anything reads
        // $token->expires_at: an unknown or malformed token used to
        // reach that read first and crash with an uncaught error
        // (null->expires_at) instead of the intended 500 response
        // below - handing an attacker fuzzing this endpoint a stack
        // trace instead of a clean error.
        if (!is_null($token)) {
            $duration = (new \DateTime())->diff($token->expires_at);

            if (
                intval($duration->format('%R%i')) >= 0 &&
                $token->tokenable::class === config('crud.user_model')
            ) {
                $token->tokenable->password = $request->get('password');

                if ($token->tokenable->save()) {
                    // The token used to be left untouched here: as long
                    // as 'expires_at' hadn't passed, the very same reset
                    // link could be replayed any number of times. It is
                    // now deleted on first successful use, same as any
                    // one-time token should be.
                    $token->delete();

                    $this->setResponse(trans('rivet::pwd.renew'), 200);
                }
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
            $this->setResponse(trans('rivet::pwd.renew'), 200);
        }

        return $this->response->format();
    }
}
