<?php
/**
 * TwoFactorLoginChallenger class file
 *
 * PHP Version 8.1
 *
 * @category Service
 * @package  Rivet\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rivet\Contracts\Auth\LoginChallenger;
use Rivet\Data\Models\Auth\User;

/**
 * Rivet's native two-factor authentication, as a LoginChallenger:
 * bound by default in LaravelServiceProvider, so login() always
 * consults it, with config('two_factor.enabled') as the master
 * switch. Not a separate package - see docs/USAGE.md for the design
 * reasoning (this exists as an implementation of LoginChallenger
 * rather than inline in AuthController so the extension point itself
 * stays usable by anything wanting to replace this behaviour entirely).
 *
 * @category Service
 * @package  Rivet\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TwoFactorLoginChallenger implements LoginChallenger
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
     * {@inheritdoc}
     *
     * @param User    $user    The user whose credentials were just validated
     * @param Request $request The login request
     *
     * @return JsonResponse|null
     */
    public function challenge(User $user, Request $request): ?JsonResponse
    {
        if (!config('two_factor.enabled') || $this->service->isExempt($user)) {
            return null;
        }

        $confirmed = $user->twoFactorMethods()->whereNotNull('confirmed_at')->get();

        if ($confirmed->isNotEmpty()) {
            return $this->pendingResponse(
                $user, 'verify', $confirmed->pluck('method')->all()
            );
        }

        if (config('two_factor.force_enrollment')) {
            return $this->pendingResponse(
                $user, 'enroll', config('two_factor.available_methods')
            );
        }

        return null;
    }

    /**
     * Build the {meta, data} response carrying a pending token, in the
     * same envelope shape as every other Rivet endpoint.
     *
     * @param User     $user    The user
     * @param string   $intent  'verify' or 'enroll'
     * @param string[] $methods The user's confirmed methods (intent
     *                          'verify'), or the project's configured
     *                          available methods to choose from (intent
     *                          'enroll' - nothing is confirmed yet)
     *
     * @return JsonResponse
     */
    private function pendingResponse(User $user, string $intent, array $methods): JsonResponse
    {
        return (new ResponseService([
            'pending_token' => $this->service->issuePendingToken($user, $intent),
            'intent'        => $intent,
            'methods'       => $methods
        ]))->format();
    }
}
