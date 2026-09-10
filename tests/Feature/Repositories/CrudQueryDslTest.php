<?php
/**
 * CrudQueryDslTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Repositories;

use Rivet\Tests\TestCase;
use Rivet\Tests\Fixtures\Http\Controllers\WidgetController;
use Rivet\Tests\Fixtures\Models\Widget;
use Rivet\Tests\Fixtures\Models\WidgetCategory;

/**
 * Characterization tests for CRUD's filter/sort DSL: operators beyond
 * 'lk', bitwise AND/OR combination, and relation-based filtering/sorting.
 *
 * One filtered request per test method, deliberately: Laravel caches a
 * Route's resolved controller instance on the Route object itself, so
 * several filtered requests within one test method would hit the same
 * repository/query-builder instance and accumulate conditions across
 * calls instead of starting fresh each time.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class CrudQueryDslTest extends TestCase
{
    /**
     * @param \Illuminate\Routing\Router $router The router
     *
     * @return void
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('api')->group(function ($router) {
            $router->get('widgets', [ WidgetController::class, 'list' ]);
        });
    }

    /**
     * @return void
     */
    public function testGreaterThanOperator(): void
    {
        $a = Widget::create([ 'name' => 'A' ]);
        Widget::create([ 'name' => 'B' ]);
        Widget::create([ 'name' => 'C' ]);

        $this->getJson("widgets?filters=id:gt({$a->id})")
            ->assertJsonCount(2, 'data');
    }

    /**
     * @return void
     */
    public function testGreaterThanOrEqualOperator(): void
    {
        Widget::create([ 'name' => 'A' ]);
        $b = Widget::create([ 'name' => 'B' ]);
        Widget::create([ 'name' => 'C' ]);

        $this->getJson("widgets?filters=id:gte({$b->id})")
            ->assertJsonCount(2, 'data');
    }

    /**
     * @return void
     */
    public function testLessThanOperator(): void
    {
        Widget::create([ 'name' => 'A' ]);
        Widget::create([ 'name' => 'B' ]);
        $c = Widget::create([ 'name' => 'C' ]);

        $this->getJson("widgets?filters=id:lt({$c->id})")
            ->assertJsonCount(2, 'data');
    }

    /**
     * @return void
     */
    public function testNotEqualOperator(): void
    {
        Widget::create([ 'name' => 'A' ]);
        $b = Widget::create([ 'name' => 'B' ]);
        Widget::create([ 'name' => 'C' ]);

        $this->getJson("widgets?filters=id:neq({$b->id})")
            ->assertJsonCount(2, 'data');
    }

    /**
     * @return void
     */
    public function testInOperator(): void
    {
        $a = Widget::create([ 'name' => 'A' ]);
        Widget::create([ 'name' => 'B' ]);
        $c = Widget::create([ 'name' => 'C' ]);

        $response = $this->getJson("widgets?filters=id:in({$a->id},{$c->id})");

        $response->assertJsonCount(2, 'data');
    }

    /**
     * @return void
     */
    public function testBetweenOperator(): void
    {
        $a = Widget::create([ 'name' => 'A' ]);
        Widget::create([ 'name' => 'B' ]);
        $c = Widget::create([ 'name' => 'C' ]);

        $response = $this->getJson("widgets?filters=id:btw({$a->id},{$c->id})");

        $response->assertJsonCount(3, 'data');
    }

    /**
     * @return void
     */
    public function testNullOperator(): void
    {
        $category = WidgetCategory::create([ 'name' => 'Tools' ]);
        Widget::create([ 'name' => 'Loose' ]);
        $categorized = Widget::create([ 'name' => 'Sorted' ]);
        $categorized->category()->associate($category);
        $categorized->save();

        $response = $this->getJson('widgets?filters=category_raw:n(1)');

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Loose');
    }

    /**
     * @return void
     */
    public function testIsTrueOperator(): void
    {
        Widget::create([ 'name' => 'On', 'is_active' => true ]);
        Widget::create([ 'name' => 'Off', 'is_active' => false ]);

        $this->getJson('widgets?filters=active:ist(1)')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'On');
    }

    /**
     * @return void
     */
    public function testIsFalseOperator(): void
    {
        Widget::create([ 'name' => 'On', 'is_active' => true ]);
        Widget::create([ 'name' => 'Off', 'is_active' => false ]);

        $this->getJson('widgets?filters=active:isf(1)')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Off');
    }

    /**
     * The '|u|' bitwise syntax for OR between two top-level conditions.
     *
     * @return void
     */
    public function testOrBitwiseCombination(): void
    {
        Widget::create([ 'name' => 'Alpha' ]);
        Widget::create([ 'name' => 'Beta' ]);
        Widget::create([ 'name' => 'Gamma' ]);

        $response = $this->getJson(
            'widgets?filters=name:lk(Alpha)|u|name:lk(Beta)'
        );

        $response->assertJsonCount(2, 'data');
    }

    /**
     * The '|n|' bitwise syntax for explicit AND between two top-level
     * conditions - this DSL has no implicit AND from a plain space;
     * '|n|' (like '|u|' for OR above) is the only way to combine two
     * top-level conditions explicitly.
     *
     * @return void
     */
    public function testExplicitAndBitwiseCombination(): void
    {
        Widget::create([ 'name' => 'Match', 'is_active' => true ]);
        Widget::create([ 'name' => 'Match', 'is_active' => false ]);
        Widget::create([ 'name' => 'Other', 'is_active' => true ]);

        $response = $this->getJson(
            'widgets?filters=name:lk(Match)|n|active:ist(1)'
        );

        $response->assertJsonCount(1, 'data');
    }

    /**
     * Filtering by a field on the related model - CRUD's dynamic join
     * resolution via reflection (_getRelation/_setQueryJoin), the
     * DSL's most complex code path.
     *
     * @return void
     */
    public function testFilteringByRelatedModelField(): void
    {
        $tools = WidgetCategory::create([ 'name' => 'Tools' ]);
        $toys = WidgetCategory::create([ 'name' => 'Toys' ]);

        $hammer = Widget::create([ 'name' => 'Hammer' ]);
        $hammer->category()->associate($tools);
        $hammer->save();

        $yoyo = Widget::create([ 'name' => 'Yoyo' ]);
        $yoyo->category()->associate($toys);
        $yoyo->save();

        $response = $this->getJson('widgets?filters=category.name:eq(Tools)');

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Hammer');
    }

    /**
     * Sorting by a field on the related model - same join machinery as
     * the filter test above, exercised through _setQueryOrder instead.
     *
     * @return void
     */
    public function testSortingByRelatedModelField(): void
    {
        $b_category = WidgetCategory::create([ 'name' => 'B Category' ]);
        $a_category = WidgetCategory::create([ 'name' => 'A Category' ]);

        $first = Widget::create([ 'name' => 'First' ]);
        $first->category()->associate($b_category);
        $first->save();

        $second = Widget::create([ 'name' => 'Second' ]);
        $second->category()->associate($a_category);
        $second->save();

        $response = $this->getJson('widgets?sort=category.name');

        $response->assertJsonPath('data.0.name', 'Second');
        $response->assertJsonPath('data.1.name', 'First');
    }
}
