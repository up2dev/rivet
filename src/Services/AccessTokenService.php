<?php
/**
 * AccessTokenService class file
 *
 * PHP Version 8.1
 *
 * @category Service
 * @package  Rivet\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Rivet\Data\Models\Auth\User;

/**
 * AccessTokenService
 *
 * Single place issuing Sanctum access tokens (login, 2FA, refresh), so
 * every path applies the same expiration, pruning and response body.
 *
 * @category Service
 * @package  Rivet\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class AccessTokenService
{
    /**
     * Issue a token for a user and return the login response body.
     *
     * @param User    $user    The user
     * @param Request $request The request (user agent)
     * @param bool    $prune   Remove expired tokens and this device's previous one
     *
     * @return array
     */
    public function issue(User $user, Request $request, bool $prune = true): array
    {
        if ($prune) {
            $this->prune($user, $request);
        }

        foreach (config('query.relations', []) as $relation) {
            $user->loadMissing($relation);
        }

        $token = $user->createToken(
            Hash::make((string) $request->server('HTTP_USER_AGENT')),
            [ '*' ],
            $this->expiresAt()
        );

        return [
            'token'      => $token->plainTextToken,
            'token_type' => 'bearer',
            'expires_at' => (
                is_null($token->accessToken->expires_at)? null: (
                    new \DateTime($token->accessToken->expires_at)
                )->format('Y-m-d\TH:i:s.u\Z')
            ),
            'user'       => $user
        ];
    }

    /**
     * Delete the user's expired tokens and the one of the same user agent.
     *
     * @param User    $user    The user
     * @param Request $request The request (user agent)
     *
     * @return void
     */
    public function prune(User $user, Request $request): void
    {
        $user_agent = (string) $request->server('HTTP_USER_AGENT');

        foreach ($user->tokens()->get() as $access_token) {
            if (
                (
                    !is_null($access_token->expires_at) &&
                    $access_token->expires_at->isPast()
                ) ||
                Hash::check($user_agent, $access_token->name)
            ) {
                $access_token->delete();
            }
        }
    }

    /**
     * Expiration of a new token ('expiration_override' wins).
     *
     * @return \Illuminate\Support\Carbon|null
     */
    public function expiresAt(): ?\Illuminate\Support\Carbon
    {
        $minutes = config('sanctum.expiration_override') ?? config('sanctum.expiration');

        return is_null($minutes)? null: now()->addMinutes((int) $minutes);
    }
}
