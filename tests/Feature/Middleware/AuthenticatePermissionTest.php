<?php
/**
 * AuthenticatePermissionTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Middleware
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Middleware;

use Rivet\Tests\TestCase;
use Rivet\Data\Models\Auth\User;
use Rivet\Data\Models\Auth\Role;
use Rivet\Data\Models\Auth\Permission;
use Rivet\Data\Models\Dictionaries\Types\PermissionType;
use Illuminate\Http\Request;

/**
 * Covers Authenticate::handle()'s per-route permission check. The
 * route's expected 'uid' is computed with the real ra_to_uid() helper
 * rather than reconstructed by hand.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Middleware
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class AuthenticatePermissionTest extends TestCase
{
    /**
     * A route with NO matching Permission row at all is currently
     * open to any authenticated user - permissions are opt-in
     * (created via `rightsmanagement --action=create-permissions`),
     * not deny-by-default. Documented here as observed behaviour, not
     * asserted as correct or incorrect.
     *
     * @return void
     */
    public function testARouteWithNoRegisteredPermissionIsOpenToAnyAuthenticatedUser(): void
    {
        $user = User::create([
            'login'             => 'noperm',
            'email'             => 'noperm@example.test',
            'email_verified_at' => now(),
            'password'          => 'Passw0rd!'
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth');

        $response->assertStatus(200);
    }

    /**
     * Once a Permission row exists matching the route, a user with NO
     * role granting that permission is rejected with 403.
     *
     * @return void
     */
    public function testARouteWithARegisteredPermissionRejectsAUserWithoutIt(): void
    {
        $uid = $this->_uidForGetAuth();

        PermissionType::create([ 'uid' => 'ENDPOINT', 'name' => 'Endpoint' ]);
        Permission::create([
            'uid'                => $uid,
            'name'               => $uid,
            'permission_type_id' => PermissionType::firstWhere('uid', 'ENDPOINT')->id
        ]);

        $user = User::create([
            'login'             => 'norole',
            'email'             => 'norole@example.test',
            'email_verified_at' => now(),
            'password'          => 'Passw0rd!'
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth');

        $response->assertStatus(403);
    }

    /**
     * A user whose role is linked to the matching Permission (via the
     * role_permission pivot) is let through.
     *
     * @return void
     */
    public function testARouteWithARegisteredPermissionAllowsAUserWithTheRightRole(): void
    {
        $uid = $this->_uidForGetAuth();

        PermissionType::create([ 'uid' => 'ENDPOINT', 'name' => 'Endpoint' ]);
        $permission = Permission::create([
            'uid'                => $uid,
            'name'               => $uid,
            'permission_type_id' => PermissionType::firstWhere('uid', 'ENDPOINT')->id
        ]);

        $role = Role::create([ 'uid' => 'VIEWER', 'name' => 'Viewer' ]);
        $role->permissions()->attach($permission->id);

        $user = User::create([
            'login'             => 'hasrole',
            'email'             => 'hasrole@example.test',
            'email_verified_at' => now(),
            'password'          => 'Passw0rd!'
        ]);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth');

        $response->assertStatus(200);
    }

    /**
     * Compute the real permission uid for GET /api/auth using the
     * actual ra_to_uid() helper against the actual registered route.
     *
     * @return string
     */
    private function _uidForGetAuth(): string
    {
        $route = app('router')->getRoutes()->match(
            Request::create('/api/auth', 'GET')
        );

        return ra_to_uid($route);
    }
}
