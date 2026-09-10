<?php
/**
 * TwoFactorService class file
 *
 * PHP Version 8.1
 *
 * @category Service
 * @package  Rivet\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Rivet\Data\Models\Auth\User;

/**
 * Two-factor authentication support: the TOTP algorithm (delegated to
 * pragmarx/google2fa), the pending-token mechanism used between
 * password validation and 2FA verification/enrollment, and the
 * exemption permission check.
 *
 * @category Service
 * @package  Rivet\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TwoFactorService
{
    /**
     * Cache key prefix for a pending token's payload.
     *
     * @var string
     */
    private const CACHE_PREFIX = 'two_factor_pending:';

    /**
     * Generate a new TOTP secret.
     *
     * @return string
     */
    public function generateSecret(): string
    {
        return (new Google2FA())->generateSecretKey();
    }

    /**
     * Build the otpauth:// URI an authenticator app scans as a QR code.
     * Rendering the QR code image itself is left to the client - this
     * package returns the URI text only, never a service call to a
     * third party.
     *
     * @param User   $user   The enrolling user
     * @param string $secret The TOTP secret
     *
     * @return string
     */
    public function qrCodeUri(User $user, string $secret): string
    {
        return (new Google2FA())->getQRCodeUrl(
            config('two_factor.issuer'), $user->email, $secret
        );
    }

    /**
     * Verify a TOTP code against a secret.
     *
     * @param string $secret The TOTP secret
     * @param string $code   The code submitted by the user
     *
     * @return bool
     */
    public function verifyTotp(string $secret, string $code): bool
    {
        return (new Google2FA())->verifyKey($secret, $code) !== false;
    }

    /**
     * Generate a numeric one-time code for the email method.
     *
     * @return string
     */
    public function generateEmailCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    /**
     * Store an email code for later verification, replacing any
     * previous one for this user - only one active code at a time.
     *
     * @param User   $user The user
     * @param string $code The code just sent
     *
     * @return void
     */
    public function storeEmailCode(User $user, string $code): void
    {
        Cache::put(
            "two_factor_email_code:{$user->id}", $code,
            now()->addMinutes(config('two_factor.email_code_ttl'))
        );
    }

    /**
     * Verify an email code against the one last stored for this user,
     * invalidating it either way - a code is single-use.
     *
     * @param User   $user The user
     * @param string $code The code submitted by the user
     *
     * @return bool
     */
    public function verifyEmailCode(User $user, string $code): bool
    {
        $key = "two_factor_email_code:{$user->id}";
        $stored = Cache::get($key);
        Cache::forget($key);

        return !is_null($stored) && hash_equals($stored, $code);
    }

    /**
     * Whether the user is exempt from two-factor entirely (both the
     * verification step and the forced-enrollment requirement), via
     * config('two_factor.bypass_permission').
     *
     * @param User $user The user to check
     *
     * @return bool
     */
    public function isExempt(User $user): bool
    {
        $permission = config('two_factor.bypass_permission');

        if (empty($permission)) {
            return false;
        }

        return $user->roles()->whereHas(
            'permissions', fn ($q) => $q->where('uid', $permission)
        )->exists();
    }

    /**
     * Issue a pending token, storing what it authorizes (which user,
     * and for what: completing verification of an already-enrolled
     * method, or completing enrollment itself).
     *
     * @param User   $user   The user this token is for
     * @param string $intent 'verify' or 'enroll'
     *
     * @return string
     */
    public function issuePendingToken(User $user, string $intent): string
    {
        $token = Str::random(64);

        Cache::put(
            self::CACHE_PREFIX.$token,
            [ 'user_id' => $user->id, 'intent' => $intent ],
            now()->addMinutes(config('two_factor.pending_token_ttl'))
        );

        return $token;
    }

    /**
     * Resolve a pending token into its stored payload, or null if it
     * doesn't exist or has expired.
     *
     * @param string $token The pending token
     *
     * @return array{user_id: int, intent: string}|null
     */
    public function resolvePendingToken(string $token): ?array
    {
        return Cache::get(self::CACHE_PREFIX.$token);
    }

    /**
     * Invalidate a pending token - called once it has been consumed
     * (successful verification/enrollment), so it cannot be reused.
     *
     * @param string $token The pending token
     *
     * @return void
     */
    public function invalidatePendingToken(string $token): void
    {
        Cache::forget(self::CACHE_PREFIX.$token);
    }

    /**
     * Issue a real Sanctum token for a user who has just completed
     * two-factor verification/enrollment - the same token shape
     * AuthController::login() produces on a direct (no 2FA) login, so
     * a client sees no difference in the final response either way.
     *
     * @param User    $user    The user to issue a token for
     * @param Request $request The current request (for the user-agent)
     *
     * @return array
     */
    public function issueToken(User $user, Request $request): array
    {
        $token = $user->createToken(
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
}
