<?php
/**
 * Authenticate class file
 *
 * PHP Version 8.1
 *
 * @category Middleware
 * @package  Rivet\Http\Middleware
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Rivet\Data\Models\Auth\Permission;
use Rivet\Services\ResponseService;

/**
 * Authenticate
 *
 * @category Middleware
 * @package  Rivet\Http\Middleware
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request  $request    The request
     * @param Closure  $next       The controller method passed in routes
     * @param string[] ...$guards  The guard(s) name(s) separeted by points (.)
     *
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);

        if (Route::current()->uri === 'api/auth') {
            Route::current()->setParameter('uid', auth()->user()->id);
        }

        if (!is_null(auth()->user())) {
            if (!auth()->user()->is_active) {
                return (new ResponseService(trans('rivet::auth.inactive'), 400))->format();
            } elseif (config('auth.is_mail_locked') && is_null(auth()->user()->email_verified_at)) {
                return (new ResponseService(trans('rivet::auth.email'), 400))->format();
            }

            $method = ra_to_uid(Route::current());
            $permission = Permission::where(
                'uid', 'LIKE', "{$method}%"
            )->get();

            if ($permission->isEmpty()) {
                $method = Str::beforeLast($method, '_');
                $permission = Permission::where('uid', 'LIKE', "{$method}%")->get();
            }

            if ($permission->isNotEmpty()) {
                $permission = Permission::join('role_permission as rperm', function ($join) {
                    $join->on('rperm.permission_id', '=', 'permissions.id');
                    $join->whereIn(
                        'rperm.role_id', auth()->user()->roles->pluck('id')->toArray()
                    );
                })->whereIn(
                    'uid', $permission->pluck('uid')->toArray()
                )->first();

                if (is_null($permission)) {
                    return (new ResponseService(trans('rivet::auth.403'), 403))->format();
                }

                $filters = Str::of(Str::after(
                    $permission->uid, $method
                ))->explode('_');

                config([ 'auth.filters' => $filters->toArray() ]);
            }
        }

        return $next($request);
    }
}
