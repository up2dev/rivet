<?php
/**
 * UserController class file
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
use Rivet\Http\Controllers\BaseController;

/**
 * UserController
 *
 * @category Controller
 * @package  Rivet\Http\Controllers\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class UserController extends BaseController
{
    /**
     * Method called by the /api/auth/user/email/{token} URL in POST.
     *
     * @param string  $token   The valid token
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function validate(string $token, Request $request): JsonResponse
    {
        $user_model = config('crud.user_model');
        $user = $user_model::firstWhere('email_token', $token);

        $this->setResponse(trans('rivet::user.unverified'), 500);

        if (!is_null($user)) {
            $user->email_verified_at = new \DateTime();
            $user->save();

            $this->setResponse(trans('rivet::user.login'), 200);
        }

        return $this->response->format();
    }
}
