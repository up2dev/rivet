<?php
/**
 * AuthController class file
 *
 * PHP Version 8.1
 *
 * @category Controller
 * @package  Rivet\Http\Controllers\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Http\Controllers\Auth;

use Rivet\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Rivet\Contracts\Auth\LoginChallenger;
use Rivet\Data\Models\Auth\User;

/**
 * AuthController
 *
 * @category Controller
 * @package  Rivet\Http\Controllers\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class AuthController extends BaseController
{
    /**
     * Method called by the /api/auth/login URL in POST.
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $user_model = config('crud.user_model');

        // Reading via config() rather than env() directly: env() calls
        // outside of a config/*.php file return null as soon as
        // `php artisan config:cache` has run (standard in production),
        // which silently forced this into the case-insensitive branch
        // in any cached-config production environment regardless of
        // the IS_LOGIN_KS value actually set.
        if (config('auth.login_case_sensitive')) {
            $user = $user_model::where('login', $request->login);
        } else {
            $user = $user_model::where(DB::raw('LOWER(login)'), Str::lower($request->login));
        }

        $relations = empty($relations)? config('query.relations', []): $relations;

        foreach ($relations as $relation) {
            $user->with($relation);
        }

        $user = $user->first();

        if (
            !$user || !is_null($user->deleted_at) ||
            !Hash::check($request->password, $user->password)
        ) {
            $this->setResponse(trans('rivet::auth.failed'), 400);
        } elseif (!$user->is_active) {
            $this->setResponse(trans('rivet::auth.inactive'), 400);
        } elseif (
            config('auth.is_mail_locked') && is_null($user->email_verified_at)
        ) {
            $this->setResponse(trans('rivet::auth.email'), 400);
        } else {
            if (app()->bound(LoginChallenger::class)) {
                $challenge = app(LoginChallenger::class)->challenge($user, $request);

                if (!is_null($challenge)) {
                    return $challenge;
                }
            }

            foreach ($user->tokens()->getResults() as $access_token) {
                if (
                    Hash::check(
                        $request->server('HTTP_USER_AGENT'),
                        $access_token->name
                    ) || (
                        !is_null($access_token->expires_at) &&
                        new \DateTime($access_token->expires_at) < new \DateTime()
                    )
                ) {
                    $access_token->delete();
                }
            }

            $this->setResponse(
                $this->setTokenBody($user->createToken(
                    Hash::make($request->server('HTTP_USER_AGENT')), [ '*' ],
                    (
                        is_null(config('sanctum.expiration_override'))?
                            (
                                is_null(config('sanctum.expiration'))?
                                    null:
                                    now()->addMinutes(config('sanctum.expiration'))
                            ):
                            now()->addMinutes(config('sanctum.expiration_override'))
                    )
                ), $user)
            );
        }

        return $this->response->format();
    }

    /**
     * Method called by the /api/auth/refresh URL in GET.
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        $this->setResponse(
            $this->setTokenBody($request->user()->createToken(
                Hash::make($request->server('HTTP_USER_AGENT')), [ '*' ],
                (
                    is_null(config('sanctum.expiration'))?
                        null:
                        now()->addMinutes(config('sanctum.expiration'))
                )
            ))
        );

        return $this->response->format();
    }

    /**
     * Method called by the /api/auth/logout URL in GET.
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        $this->setResponse(trans('rivet::auth.logout'));

        return $this->response->format();
    }

    /**
     * Helper function to format the response with the token.
     *
     * @param NewAccessToken $token The token
     *
     * @return array
     */
    protected function setTokenBody(NewAccessToken $token, ?User $user = null): array
    {
        return [
            'token'      => $token->plainTextToken,
            'token_type' => 'bearer',
            'expires_at' => (
                is_null($token->accessToken->expires_at)? null: (
                    new \DateTime($token->accessToken->expires_at)
                )->format('Y-m-d\TH:i:s.u\Z')
                // 2022-11-16T13:18:20.000000Z
                // 2022-11-16T13:30:54.000000Z
            ),
            'user'       => (is_null($user)? auth()->user(): $user)
        ];
    }
}
