<?php
/**
 * RbacMassAssignmentTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Repositories;

use Rivet\Tests\TestCase;
use Rivet\Data\Repositories\Auth\RoleRepository;
use Rivet\Data\Repositories\Auth\PermissionRepository;
use Rivet\Data\Models\Dictionaries\Types\PermissionType;

/**
 * Covers $fillable enforcement on Role, Permission, and
 * PermissionType, which directly affects
 * `php artisan rightsmanagement --action=create-role` and
 * `--action=create-permissions`.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class RbacMassAssignmentTest extends TestCase
{
    /**
     * @return void
     */
    public function testRoleCanBeCreatedThroughItsRepository(): void
    {
        $repo = new RoleRepository();

        $repo->create([ 'uid' => 'EDITOR', 'name' => 'Editor' ]);

        $this->assertSame('EDITOR', $repo->getModel()->uid);
        $this->assertDatabaseHas('roles', [
            'uid' => 'EDITOR', 'name' => 'Editor'
        ]);
    }

    /**
     * @return void
     */
    public function testPermissionCanBeCreatedThroughItsRepository(): void
    {
        $type = PermissionType::create([
            'uid' => 'ENDPOINT', 'name' => 'Endpoint'
        ]);

        $repo = new PermissionRepository();

        $repo->create([
            'uid'                => 'APP_WIDGET',
            'name'               => 'APP_WIDGET',
            'permission_type_id' => $type->id
        ]);

        $this->assertSame('APP_WIDGET', $repo->getModel()->uid);
        $this->assertDatabaseHas('permissions', [
            'uid' => 'APP_WIDGET', 'permission_type_id' => $type->id
        ]);
    }

    /**
     * @return void
     */
    public function testPermissionTypeCanBeCreatedDirectly(): void
    {
        PermissionType::create([ 'uid' => 'MENU', 'name' => 'Menu' ]);

        $this->assertDatabaseHas('permission_types', [
            'uid' => 'MENU', 'name' => 'Menu'
        ]);
    }
}
