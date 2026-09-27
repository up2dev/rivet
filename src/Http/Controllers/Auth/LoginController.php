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
        $email = Str::lower((string) $request->get('email'));
        $logins = $user_model::where(DB::raw('LOWER(email)'), $email)
            ->pluck('login')->toArray();

        // Sent to the requested address (it used to go to MAIL_FROM), and
        // only if it matches an account. Same response either way.
        if (!empty($logins)) {
            Mail::send(new BaseMail('rivet::emails.user.logins', [
                'logins'       => $logins,
                'to_addresses' => [ [ 'email' => $email, 'name' => $email ] ],
                'subject'      => trans('rivet::mail.subject_user_logins')
            ]));
        }

        // No response used to be set here: the endpoint always crashed.
        $this->setResponse(trans('rivet::user.logins_sent'));

        return $this->response->format();
    }
}
