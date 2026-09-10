<?php
/**
 * MassAssignmentProtectionTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Repositories;

use Rivet\Tests\TestCase;
use Rivet\Tests\Fixtures\Repositories\WidgetRepository;

/**
 * MassAssignmentProtectionTest
 *
 * Before the fix, CRUD::defaultRegister() wrote any field whose name
 * matched an existing DB column directly onto the model
 * ($this->model->$field = $value), bypassing $fillable entirely. A
 * client could set any existing column as long as the route's
 * Validator didn't happen to reject that field name.
 *
 * RED (against the pre-fix code): both tests below would fail -
 * 'secret_internal_flag' would end up written to the database despite
 * not being declared $fillable on Widget.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class MassAssignmentProtectionTest extends TestCase
{
    /**
     * A field that exists as a DB column but isn't declared $fillable
     * must be silently ignored, not written.
     *
     * @return void
     */
    public function testItIgnoresFieldsNotDeclaredFillableOnCreate(): void
    {
        $repo = new WidgetRepository();

        $repo->create([
            'name'                  => 'Acme Widget',
            'secret_internal_flag'  => 'should-not-be-writable'
        ]);

        $model = $repo->getModel();

        $this->assertSame('Acme Widget', $model->name);
        $this->assertNull($model->secret_internal_flag);
        $this->assertDatabaseHas('widgets', [
            'name'                 => 'Acme Widget',
            'secret_internal_flag' => null
        ]);
    }

    /**
     * The fix must not break legitimate writes to fields that ARE
     * declared $fillable - this is the test that would have caught an
     * overly broad fix (e.g. blocking everything).
     *
     * @return void
     */
    public function testItStillWritesDeclaredFillableFieldsOnUpdate(): void
    {
        $repo = new WidgetRepository();
        $repo->create([ 'name' => 'Original' ]);
        $id = $repo->getModel()->id;

        $repo->update([ 'name' => 'Renamed' ], $id);

        $this->assertSame('Renamed', $repo->getModel()->fresh()->name);
    }

    /**
     * Same protection must hold for massCreate(), which loops over
     * create() internally - guards against a fix applied only to the
     * single-item path.
     *
     * @return void
     */
    public function testMassCreateAlsoIgnoresNonFillableFields(): void
    {
        $repo = new WidgetRepository();

        $repo->massCreate([
            [ 'name' => 'One', 'secret_internal_flag' => 'x' ],
            [ 'name' => 'Two', 'secret_internal_flag' => 'y' ]
        ]);

        $this->assertDatabaseHas('widgets', [
            'name' => 'One', 'secret_internal_flag' => null
        ]);
        $this->assertDatabaseHas('widgets', [
            'name' => 'Two', 'secret_internal_flag' => null
        ]);
    }
}
