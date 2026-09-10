<?php
/**
 * MakeCrudCommandTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Console
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Console;

use Rivet\Tests\TestCase;

/**
 * Covers rivet:make:crud's table introspection: relation detection
 * from foreign keys, $filters pre-fill, and the unique:/max:/email
 * validation rule inference - against the 'crud_gen_articles' fixture
 * table (see tests/Fixtures/Migrations), which has a foreign key, a
 * unique varchar column, and a nullable text column specifically to
 * exercise these.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Console
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class MakeCrudCommandTest extends TestCase
{
    /**
     * Generated file paths, tracked so tearDown() can remove them
     * regardless of which assertions ran.
     *
     * @var string[]
     */
    private array $generatedPaths = [];

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->generatedPaths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testGeneratesABelongsToRelationFromTheForeignKey(): void
    {
        $this->artisan(
            'rivet:make:crud', [ 'table' => 'crud_gen_articles', '--force' => true ]
        )->assertSuccessful();

        $model = $this->_readGenerated('app/Data/Models/CrudGenArticle.php');

        $this->assertStringContainsString('public function author():', $model);
        $this->assertStringContainsString(
            'return $this->belongsTo(User::class);', $model
        );
    }

    /**
     * @return void
     */
    public function testPrefillsFiltersFromColumnsAndRelations(): void
    {
        $this->artisan(
            'rivet:make:crud', [ 'table' => 'crud_gen_articles', '--force' => true ]
        )->assertSuccessful();

        $repository = $this->_readGenerated(
            'app/Data/Repositories/CrudGenArticleRepository.php'
        );

        $this->assertStringContainsString("'title' => 'title'", $repository);
        $this->assertStringContainsString(
            "'author' => 'relation.author'", $repository
        );
        // The foreign key column itself is superseded by the relation
        // entry above, not also listed as a plain column.
        $this->assertStringNotContainsString(
            "'author_id' => 'author_id'", $repository
        );
    }

    /**
     * unique: is derivable on SQLite (Schema::getIndexes() reports it
     * regardless of how the column was declared), but max: is not for
     * a column created via Schema::string($name, $length): Laravel's
     * SQLite grammar drops the length from the DDL entirely (confirmed
     * against the raw CREATE TABLE statement - 'varchar', never
     * 'varchar(120)'), so there's nothing for _sqliteColumnLength() to
     * find here. See testDetectsMaxLengthFromRawSqlDdl() below for
     * where max: itself is actually exercised.
     *
     * @return void
     */
    public function testInfersUniqueAndNullableValidationRules(): void
    {
        $this->artisan(
            'rivet:make:crud', [ 'table' => 'crud_gen_articles', '--force' => true ]
        )->assertSuccessful();

        $validator = $this->_readGenerated(
            'app/Data/Validators/CrudGenArticleValidator.php'
        );

        $this->assertStringContainsString(
            "'title' => 'required|string|unique:crud_gen_articles,title'",
            $validator
        );
        $this->assertStringContainsString("'body' => 'nullable|string'", $validator);
    }

    /**
     * max: relies on a length actually being present in the table's
     * DDL - true for a table created via raw SQL (as tested here), but
     * not for one created through Schema::string($name, $length) on
     * SQLite (see the test above). This is what proves
     * _sqliteColumnLength()'s own parsing logic is correct, on the one
     * kind of table where it can find anything at all.
     *
     * @return void
     */
    public function testDetectsMaxLengthFromRawSqlDdl(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'CREATE TABLE crud_gen_raw_widgets ('.
            'id INTEGER PRIMARY KEY AUTOINCREMENT, '.
            'label VARCHAR(50) NOT NULL, '.
            'created_at DATETIME, updated_at DATETIME)'
        );

        $this->artisan(
            'rivet:make:crud', [ 'table' => 'crud_gen_raw_widgets', '--force' => true ]
        )->assertSuccessful();

        $validator = $this->_readGenerated(
            'app/Data/Validators/CrudGenRawWidgetValidator.php'
        );

        $this->assertStringContainsString(
            "'label' => 'required|string|max:50'", $validator
        );
    }

    /**
     * Read a file generated under the Testbench workbench's base_path()
     * and record it for cleanup.
     *
     * @param string $relative_path The path, relative to base_path()
     *
     * @return string
     */
    private function _readGenerated(string $relative_path): string
    {
        $path = base_path($relative_path);
        $this->generatedPaths[] = $path;

        $this->assertFileExists($path);

        return file_get_contents($path);
    }
}
