<?php
/**
 * LoginController class file
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
use Rivet\Http\Controllers\BaseController;
use Rivet\Mail\BaseMail;

/**
 * LoginController
 *
 * @category Controller
 * @package  Rivet\Http\Controllers\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class LoginController extends BaseController
{
    /**
     * Method called by the /api/user/login URL in POST.
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
    public function forgot(Request $request): JsonResponse
    {
        $user_model = config('crud.user_model');
        Mail::send(new BaseMail('rivet::emails.user.logins', [
            'logins' => $user_model::where(
                DB::raw('LOWER(email)'), Str::lower($request->email)
            )->get()->pluck('login')->toArray(),
            'subject' => trans('rivet::mail.subject_user_logins')
        ]));

        return $this->response->format();
    }
}
