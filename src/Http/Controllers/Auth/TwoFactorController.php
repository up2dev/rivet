<?php
/**
 * TwoFactorController class file
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
use Illuminate\Support\Facades\Mail;
use Rivet\Data\Models\Auth\TwoFactorMethod;
use Rivet\Data\Models\Auth\User;
use Rivet\Http\Controllers\BaseController;
use Rivet\Mail\BaseMail;
use Rivet\Services\TwoFactorService;

/**
 * Enrollment and verification for Rivet's native two-factor
 * authentication. Reachable two ways depending on the action: a
 * normally-authenticated user managing their own settings ('lpfauth:
 * sanctum'), or a user mid-login carrying a pending token issued by
 * TwoFactorLoginChallenger (see ResolvePendingTwoFactorToken) - see
 * _targetUser() for how the two are reconciled.
 *
 * @category Controller
 * @package  Rivet\Http\Controllers\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TwoFactorController extends BaseController
{
    /**
     * The two-factor service.
     *
     * @var TwoFactorService
     */
    private TwoFactorService $service;

    /**
     * @param TwoFactorService $service The two-factor service
     */
    public function __construct(TwoFactorService $service)
    {
        parent::__construct();

        $this->service = $service;
    }

    /**
     * POST /auth/2fa/totp/setup - generate a new TOTP secret and its
     * QR code URI. Does not confirm the method yet: a fresh,
     * unconfirmed row is created (or overwritten if one already
     * existed unconfirmed), pending a code submitted to confirm().
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $this->_targetUser($request);

        if (is_null($user)) {
            return $this->_unauthorized();
        }

        if ($this->_hasConfirmed($user, 'totp')) {
            $this->setResponse(trans('rivet::two_factor.already_confirmed'), 400);

            return $this->response->format();
        }

        $secret = $this->service->generateSecret();

        TwoFactorMethod::updateOrCreate(
            [ 'user_id' => $user->id, 'method' => 'totp' ],
            [ 'secret' => $secret, 'confirmed_at' => null ]
        );

        $this->setResponse([
            'secret'   => $secret,
            'qr_uri'   => $this->service->qrCodeUri($user, $secret)
        ]);

        return $this->response->format();
    }

    /**
     * POST /auth/2fa/totp/confirm - confirm a pending TOTP enrollment
     * with a code. If reached with a pending-enroll token (forced
     * enrollment at login), this also completes login: a real Sanctum
     * token is issued in the same response.
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function confirmTotp(Request $request): JsonResponse
    {
        $user = $this->_targetUser($request);

        if (is_null($user)) {
            return $this->_unauthorized();
        }

        $method = $user->twoFactorMethods()
            ->where('method', 'totp')->whereNull('confirmed_at')->first();

        if (is_null($method) || !$this->service->verifyTotp(
            $method->secret, (string) $request->input('code')
        )) {
            $this->setResponse(trans('rivet::two_factor.invalid_code'), 400);

            return $this->response->format();
        }

        $method->confirmed_at = now();
        $method->save();

        return $this->_completeIfEnrolling($request, $user);
    }

    /**
     * POST /auth/2fa/email/enable - create a pending email method and
     * send the first code.
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function enableEmail(Request $request): JsonResponse
    {
        $user = $this->_targetUser($request);

        if (is_null($user)) {
            return $this->_unauthorized();
        }

        if ($this->_hasConfirmed($user, 'email')) {
            $this->setResponse(trans('rivet::two_factor.already_confirmed'), 400);

            return $this->response->format();
        }

        TwoFactorMethod::updateOrCreate(
            [ 'user_id' => $user->id, 'method' => 'email' ],
            [ 'secret' => null, 'confirmed_at' => null ]
        );

        $this->_sendEmailCode($user);

        $this->setResponse(trans('rivet::two_factor.code_sent'));

        return $this->response->format();
    }

    /**
     * POST /auth/2fa/email/confirm - confirm a pending email
     * enrollment with the code just sent. Same completion behaviour
     * as confirmTotp() when reached via a pending-enroll token.
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function confirmEmail(Request $request): JsonResponse
    {
        $user = $this->_targetUser($request);

        if (is_null($user)) {
            return $this->_unauthorized();
        }

        $method = $user->twoFactorMethods()
            ->where('method', 'email')->whereNull('confirmed_at')->first();

        if (
            is_null($method) ||
            !$this->service->verifyEmailCode($user, (string) $request->input('code'))
        ) {
            $this->setResponse(trans('rivet::two_factor.invalid_code'), 400);

            return $this->response->format();
        }

        $method->confirmed_at = now();
        $method->save();

        return $this->_completeIfEnrolling($request, $user);
    }

    /**
     * POST /auth/2fa/email/request-code - (re)send a code for an
     * already-confirmed email method, for use during login
     * verification (a pending 'verify' token) - or as a resend during
     * enrollment, if the first email didn't arrive.
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function requestEmailCode(Request $request): JsonResponse
    {
        $user = $this->_targetUser($request);

        if (is_null($user)) {
            return $this->_unauthorized();
        }

        $this->_sendEmailCode($user);

        $this->setResponse(trans('rivet::two_factor.code_sent'));

        return $this->response->format();
    }

    /**
     * POST /auth/2fa/verify - verify a code against an already-
     * confirmed method, completing a login that was gated behind a
     * pending 'verify' token.
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function verify(Request $request): JsonResponse
    {
        $payload = $this->service->resolvePendingToken(
            (string) $request->input('pending_token')
        );

        if (is_null($payload) || $payload['intent'] !== 'verify') {
            return $this->_unauthorized();
        }

        $user = User::find($payload['user_id']);
        $method = (string) $request->input('method');
        $code = (string) $request->input('code');

        if (is_null($user) || !$this->_hasConfirmed($user, $method)) {
            $this->setResponse(trans('rivet::two_factor.invalid_code'), 400);

            return $this->response->format();
        }

        $valid = match ($method) {
            'totp'  => $this->service->verifyTotp(
                $user->twoFactorMethods()->where('method', 'totp')->first()->secret,
                $code
            ),
            'email' => $this->service->verifyEmailCode($user, $code),
            default => false
        };

        if (!$valid) {
            $this->setResponse(trans('rivet::two_factor.invalid_code'), 400);

            return $this->response->format();
        }

        $this->service->invalidatePendingToken($request->input('pending_token'));
        $this->setResponse($this->service->issueToken($user, $request));

        return $this->response->format();
    }

    /**
     * DELETE /auth/2fa/{method} - disable an enrolled method. Requires
     * full normal authentication ('lpfauth:sanctum') - never reachable
     * with a pending token, which would let a compromised pending
     * token disable a user's real protection.
     *
     * @param string  $method  The method to disable ('totp'/'email')
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function disable(string $method, Request $request): JsonResponse
    {
        $request->user()->twoFactorMethods()->where('method', $method)->delete();

        $this->setResponse(trans('rivet::two_factor.disabled'));

        return $this->response->format();
    }

    /**
     * Resolve who an enrollment/confirmation action is for: a pending-
     * enroll token takes priority (set by ResolvePendingTwoFactorToken
     * when the request body carries one), falling back to the
     * normally-authenticated user (self-service enrollment from
     * account settings).
     *
     * @param Request $request The request
     *
     * @return User|null
     */
    private function _targetUser(Request $request): ?User
    {
        if ($request->attributes->has('two_factor_pending_user')) {
            return $request->attributes->get('two_factor_pending_user');
        }

        return $request->user();
    }

    /**
     * @param User   $user   The user
     * @param string $method The method name
     *
     * @return bool
     */
    private function _hasConfirmed(User $user, string $method): bool
    {
        return $user->twoFactorMethods()
            ->where('method', $method)->whereNotNull('confirmed_at')->exists();
    }

    /**
     * Generate, store, and email a fresh one-time code.
     *
     * @param User $user The recipient
     *
     * @return void
     */
    private function _sendEmailCode(User $user): void
    {
        $code = $this->service->generateEmailCode();
        $this->service->storeEmailCode($user, $code);

        Mail::send(new BaseMail('rivet::emails.auth.two_factor_code', [
            'user'         => $user,
            'code'         => $code,
            'to_addresses' => [ [ 'email' => $user->email, 'name' => $user->login ] ],
            'subject'      => trans('rivet::mail.subject_two_factor_code')
        ]));
    }

    /**
     * After a method is confirmed: if this was reached via a
     * pending-enroll token, enrollment is now complete, so a real
     * Sanctum token is issued immediately - the whole point of forced
     * enrollment is that confirming a method IS the proof needed to
     * finish logging in. Self-service enrollment (already fully
     * authenticated) has nothing further to do.
     *
     * @param Request $request The request
     * @param User    $user    The user who just confirmed a method
     *
     * @return JsonResponse
     */
    private function _completeIfEnrolling(Request $request, User $user): JsonResponse
    {
        if (
            $request->attributes->get('two_factor_pending_intent') === 'enroll'
        ) {
            $this->service->invalidatePendingToken(
                $request->attributes->get('two_factor_pending_token')
            );
            $this->setResponse($this->service->issueToken($user, $request));
        } else {
            $this->setResponse(trans('rivet::two_factor.confirmed'));
        }

        return $this->response->format();
    }

    /**
     * @return JsonResponse
     */
    private function _unauthorized(): JsonResponse
    {
        $this->setResponse(trans('rivet::auth.failed'), 401);

        return $this->response->format();
    }
}
