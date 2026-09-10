<?php
/**
 * LoginChallenger interface file
 *
 * PHP Version 8.1
 *
 * @category Contract
 * @package  Rivet\Contracts\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Contracts\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rivet\Data\Models\Auth\User;

/**
 * Extension point for anything that needs to intercept a login attempt
 * after credentials have been validated but before a Sanctum token is
 * issued - a two-factor authentication step being the motivating case.
 *
 * Rivet ships no implementation and does not depend on one existing:
 * AuthController::login() only checks whether something is bound to
 * this interface in the container, and delegates to it if so. A
 * package (e.g. one adding TOTP/email-OTP 2FA) binds its own
 * implementation in its own service provider - typically only when
 * its own feature is actually enabled/configured - to hook into login
 * without Rivet depending on it, and without touching this file.
 *
 * @category Contract
 * @package  Rivet\Contracts\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
interface LoginChallenger
{
    /**
     * Called after AuthController::login() has validated credentials
     * and confirmed the account is active/verified, right before it
     * would issue a Sanctum token.
     *
     * Returning a JsonResponse short-circuits login() entirely: that
     * response is returned to the client as-is (e.g. a pending-token
     * challenge asking for a TOTP code), and no Sanctum token is
     * issued for this request. Returning null lets Rivet proceed with
     * its normal login response.
     *
     * @param User    $user    The user whose credentials were just validated
     * @param Request $request The login request
     *
     * @return JsonResponse|null
     */
    public function challenge(User $user, Request $request): ?JsonResponse;
}
