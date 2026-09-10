<?php
/**
 * DBVersionScriptExecutionTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\DBVersion
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\DBVersion;

use Rivet\Tests\TestCase;
use Rivet\Data\Models\DBVersion;
use Illuminate\Support\Facades\Schema;

/**
 * DBVersionScriptExecutionTest
 *
 * Covers the newly-implemented DBVersionTrait::bootDBVersionTrait():
 * on creation, a DBVersion's 'sqlscript' is actually executed against
 * the database; if it fails, the row itself must not be persisted -
 * exactly what the (until now unreachable) TODO comment asked for.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\DBVersion
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class DBVersionScriptExecutionTest extends TestCase
{
    /**
     * @return void
     */
    public function testCreatingWithAValidScriptRunsItAndPersistsTheVersion(): void
    {
        DBVersion::create([
            'version'   => '1.0.0',
            'sqlscript' => 'CREATE TABLE dbversion_marker (id INTEGER)'
        ]);

        $this->assertDatabaseHas('dbversions', [ 'version' => '1.0.0' ]);
        $this->assertTrue(Schema::hasTable('dbversion_marker'));
    }

    /**
     * @return void
     */
    public function testCreatingWithABrokenScriptDoesNotPersistTheVersion(): void
    {
        $version = DBVersion::create([
            'version'   => '1.0.1',
            'sqlscript' => 'THIS IS NOT VALID SQL AT ALL;'
        ]);

        $this->assertFalse($version->exists);
        $this->assertDatabaseMissing('dbversions', [ 'version' => '1.0.1' ]);
    }

    /**
     * Editing an existing, already-applied version (e.g. fixing a typo
     * in its comments) must NOT re-run its SQL script - only
     * 'creating' is hooked, not 'saving'/'updating'.
     *
     * @return void
     */
    public function testUpdatingAnExistingVersionDoesNotReRunItsScript(): void
    {
        $version = DBVersion::create([
            'version'   => '1.0.2',
            'sqlscript' => 'CREATE TABLE dbversion_marker_2 (id INTEGER)'
        ]);

        // A second run of the same CREATE TABLE would fail (table
        // already exists) if the script were re-executed on update.
        $version->comments = 'typo fix';
        $version->save();

        $this->assertDatabaseHas('dbversions', [
            'version' => '1.0.2', 'comments' => 'typo fix'
        ]);
    }
}
