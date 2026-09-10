<?php
/**
 * ResolvePendingTwoFactorToken class file
 *
 * PHP Version 8.1
 *
 * @category Middleware
 * @package  Rivet\Http\Middleware
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Rivet\Services\TwoFactorService;

/**
 * Resolves a 'pending_token' present in the request body into the user
 * and intent ('verify'/'enroll') it authorizes, attaching both to the
 * request. Deliberately permissive rather than an authentication
 * gate: a request with no pending_token (or an invalid/expired one)
 * passes through unchanged, so the same routes stay reachable by a
 * normally-authenticated user doing self-service enrollment (who
 * authenticates via 'lpfauth:sanctum' instead, resolved separately by
 * TwoFactorController::_targetUser()).
 *
 * @category Middleware
 * @package  Rivet\Http\Middleware
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class ResolvePendingTwoFactorToken
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
        $this->service = $service;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request The request
     * @param Closure $next    The next middleware
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->input('pending_token');

        if (!is_null($token)) {
            $payload = $this->service->resolvePendingToken($token);

            if (!is_null($payload)) {
                $user_model = config('crud.user_model');
                $user = $user_model::find($payload['user_id']);

                if (!is_null($user)) {
                    $request->attributes->set('two_factor_pending_user', $user);
                    $request->attributes->set('two_factor_pending_intent', $payload['intent']);
                    $request->attributes->set('two_factor_pending_token', $token);
                }
            }
        }

        return $next($request);
    }
}
