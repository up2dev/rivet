<?php
/**
 * RemainingModulesMassAssignmentTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Repositories;

use Rivet\Tests\TestCase;
use Rivet\Data\Repositories\CRONTaskRepository;
use Rivet\Data\Repositories\DBVersionRepository;
use Rivet\Data\Repositories\Dictionaries\TaxonomyRepository;
use Rivet\Data\Repositories\Dictionaries\TaxonomyValueRepository;
use Rivet\Data\Models\Dictionaries\Taxonomy;

/**
 * Covers $fillable enforcement on CRONTask, DBVersion, Taxonomy, and
 * TaxonomyValue. Log already had a correct $fillable and isn't
 * covered here.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class RemainingModulesMassAssignmentTest extends TestCase
{
    /**
     * @return void
     */
    public function testCronTaskCanBeCreatedThroughItsRepository(): void
    {
        $repo = new CRONTaskRepository();

        $repo->create([
            'uid'     => 'NIGHTLY_CLEANUP',
            'command' => 'app:cleanup',
            'minute'  => '0',
            'hour'    => '3'
        ]);

        $this->assertSame('NIGHTLY_CLEANUP', $repo->getModel()->uid);
        $this->assertDatabaseHas('crontasks', [
            'uid' => 'NIGHTLY_CLEANUP', 'command' => 'app:cleanup'
        ]);
    }

    /**
     * @return void
     */
    public function testDbVersionCanBeCreatedThroughItsRepository(): void
    {
        $repo = new DBVersionRepository();

        $repo->create([
            'version'   => '1.2.3',
            'sqlscript' => 'ALTER TABLE widgets ADD COLUMN foo INT;'
        ]);

        $this->assertSame('1.2.3', $repo->getModel()->version);
        $this->assertDatabaseHas('dbversions', [ 'version' => '1.2.3' ]);
    }

    /**
     * @return void
     */
    public function testTaxonomyCanBeCreatedThroughItsRepository(): void
    {
        $repo = new TaxonomyRepository();

        $repo->create([
            'uid'  => 'COLORS',
            'name' => 'Colors'
        ]);

        $this->assertSame('COLORS', $repo->getModel()->uid);
        $this->assertDatabaseHas('taxonomies', [ 'uid' => 'COLORS' ]);
    }

    /**
     * Mirrors CRUD's relation-association handling: 'taxonomy_id' is
     * resolved as a relation, not mass-assigned as a plain field - see
     * the comment on TaxonomyValue::$fillable.
     *
     * @return void
     */
    public function testTaxonomyValueCanBeCreatedLinkedToATaxonomy(): void
    {
        $taxonomy = Taxonomy::create([ 'uid' => 'SIZES', 'name' => 'Sizes' ]);

        $repo = new TaxonomyValueRepository();

        $repo->create([
            'uid'         => 'SIZE_M',
            'value'       => 'Medium',
            'order'       => 2,
            'taxonomy_id' => $taxonomy->id
        ]);

        $this->assertSame('Medium', $repo->getModel()->value);
        $this->assertDatabaseHas('taxonomy_values', [
            'uid' => 'SIZE_M', 'taxonomy_id' => $taxonomy->id
        ]);
    }
}
