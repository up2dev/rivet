<?php
/**
 * MiddlewareAliasTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Middleware
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Middleware;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Rivet\Data\Models\Auth\Role;
use Rivet\Http\Middleware\Authenticate;
use Rivet\Tests\TestCase;

/**
 * MiddlewareAliasTest
 *
 * The authentication middleware alias and the uids it relies on.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Middleware
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class MiddlewareAliasTest extends TestCase
{
    /**
     * @return void
     */
    public function testRivetAuthIsTheAuthenticationAlias(): void
    {
        $aliases = $this->app['router']->getMiddleware();

        $this->assertSame(Authenticate::class, $aliases['rivet.auth'] ?? null);
    }

    /**
     * Rivet's protected routes all use the alias.
     *
     * @return void
     */
    public function testProtectedRoutesUseTheAlias(): void
    {
        $route = Route::getRoutes()->match(
            request()->create('/api/auth/logout', 'GET')
        );

        $this->assertContains('rivet.auth:sanctum', $route->gatherMiddleware());
    }

    /**
     * Default permissions follow the uid scheme of Rivet's own routes.
     *
     * @return void
     */
    public function testDefaultPermissionsMatchRivetRouteUids(): void
    {
        Route::namespace('Rivet\Http\Controllers\Auth')->prefix('api/auth')
            ->group(function () {
                Route::get('role', 'RoleController@list');
                Route::get('permission', 'PermissionController@list');
            });

        $uids = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array(
                $route->uri, [ 'api/auth/role', 'api/auth/permission' ], true
            ))
            ->map(fn ($route) => ra_to_uid($route))->values();

        $this->assertTrue($uids->contains(
            fn ($uid) => Str::startsWith($uid, 'RAAR_AUTHROLE')
        ));
        $this->assertTrue($uids->contains(
            fn ($uid) => Str::startsWith($uid, 'RAAP_AUTHPERMISSION')
        ));

        foreach (Role::DEFAULT_PERMISSIONS as $uid) {
            $this->assertMatchesRegularExpression('/^RAA[RP]_AUTH/', $uid);
        }
    }
}
