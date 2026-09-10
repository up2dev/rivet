<?php
/**
 * CrudGenericActionsTest class file
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
use Illuminate\Routing\Router;

/**
 * Exercises BaseController's generic CRUD actions
 * (list/show/add/massAdd/edit/massEdit/remove/massRemove) over a real
 * HTTP request, using a fixture route table scoped to this test class.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class CrudGenericActionsTest extends TestCase
{
    /**
     * Fixture-only routes, scoped to this test class.
     *
     * @param Router $router The router
     *
     * @return void
     */
    protected function defineRoutes($router): void
    {
        // Wrapped in the 'api' middleware group: that's where the real
        // package provider pushes QueryStringToConfig
        // (Route::pushMiddlewareToGroup('api', ...) in
        // LaravelServiceProvider::boot()) - routes defined outside it
        // never get ?filters=/?sort= parsed into config('query.*') at
        // all, which is what made the filter test below see
        // unfiltered results the first time round.
        $router->middleware('api')->group(function ($router) {
            $router->get('widgets', [ WidgetController::class, 'list' ]);
            // Registered for QUERY too (RFC 10008, June 2026) alongside
            // the existing GET route - Route::query() isn't shipped as
            // a dedicated helper yet, but Route::match() registers it
            // exactly the same way.
            $router->match(
                [ 'QUERY' ], 'widgets', [ WidgetController::class, 'list' ]
            );
            $router->get('widgets/{uid}', [ WidgetController::class, 'show' ])
                ->where('uid', '[0-9]+');
            $router->post('widgets', [ WidgetController::class, 'add' ]);
            $router->post('widgets/mass', [ WidgetController::class, 'massAdd' ]);
            $router->put('widgets/{uid}', [ WidgetController::class, 'edit' ])
                ->where('uid', '[0-9]+');
            $router->put('widgets', [ WidgetController::class, 'massEdit' ]);
            $router->delete('widgets/{uid}', [ WidgetController::class, 'remove' ])
                ->where('uid', '[0-9]+');
            $router->delete('widgets', [ WidgetController::class, 'massRemove' ]);
        });
    }

    /**
     * @return void
     */
    public function testAddCreatesARecordViaFormPost(): void
    {
        $response = $this->post(
            'widgets', [ 'name' => 'First widget' ],
            [ 'Accept' => 'application/json' ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('widgets', [ 'name' => 'First widget' ]);
    }

    /**
     * @return void
     */
    public function testListReturnsAllRecords(): void
    {
        Widget::create([ 'name' => 'Alpha' ]);
        Widget::create([ 'name' => 'Beta' ]);

        $response = $this->getJson('widgets');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    /**
     * @return void
     */
    public function testShowReturnsASingleExistingRecord(): void
    {
        $widget = Widget::create([ 'name' => 'Gamma' ]);

        $response = $this->getJson("widgets/{$widget->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Gamma');
    }

    /**
     * @return void
     */
    public function testShowReturns404ForAMissingRecord(): void
    {
        $response = $this->getJson('widgets/999999');

        $response->assertStatus(404);
    }

    /**
     * @return void
     */
    public function testEditUpdatesAnExistingRecordViaFormPut(): void
    {
        $widget = Widget::create([ 'name' => 'Original' ]);

        $response = $this->put(
            "widgets/{$widget->id}", [ 'name' => 'Renamed' ],
            [ 'Accept' => 'application/json' ]
        );

        $response->assertStatus(200);
        $this->assertSame('Renamed', $widget->fresh()->name);
    }

    /**
     * @return void
     */
    public function testRemoveDeletesAnExistingRecord(): void
    {
        $widget = Widget::create([ 'name' => 'ToDelete' ]);

        $response = $this->delete(
            "widgets/{$widget->id}", [], [ 'Accept' => 'application/json' ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('widgets', [ 'id' => $widget->id ]);
    }

    /**
     * @return void
     */
    public function testMassAddCreatesSeveralRecordsAtOnce(): void
    {
        $response = $this->post(
            'widgets/mass', [
                [ 'name' => 'Mass One' ],
                [ 'name' => 'Mass Two' ]
            ],
            [ 'Accept' => 'application/json' ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('widgets', [ 'name' => 'Mass One' ]);
        $this->assertDatabaseHas('widgets', [ 'name' => 'Mass Two' ]);
    }

    /**
     * @return void
     */
    public function testMassEditUpdatesSeveralRecordsAtOnce(): void
    {
        $a = Widget::create([ 'name' => 'A' ]);
        $b = Widget::create([ 'name' => 'B' ]);

        $response = $this->put(
            'widgets', [
                [ 'id' => $a->id, 'name' => 'A-renamed' ],
                [ 'id' => $b->id, 'name' => 'B-renamed' ]
            ],
            [ 'Accept' => 'application/json' ]
        );

        $response->assertStatus(200);
        $this->assertSame('A-renamed', $a->fresh()->name);
        $this->assertSame('B-renamed', $b->fresh()->name);
    }

    /**
     * @return void
     */
    public function testMassRemoveDeletesAllRecordsWhenNoneSpecified(): void
    {
        Widget::create([ 'name' => 'One' ]);
        Widget::create([ 'name' => 'Two' ]);

        $response = $this->delete('widgets', [], [ 'Accept' => 'application/json' ]);

        $response->assertStatus(200);
        $this->assertSame(0, Widget::count());
    }

    /**
     * Exercises QueryStringToConfig + CRUD's filter DSL end-to-end,
     * not just the repository's PHP-level filtering.
     *
     * @return void
     */
    public function testListCanBeFilteredByNameViaQueryString(): void
    {
        Widget::create([ 'name' => 'Findable Widget' ]);
        Widget::create([ 'name' => 'Other' ]);

        $response = $this->getJson('widgets?filters=name:lk(Findable)');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Findable Widget');
    }

    /**
     * The HTTP QUERY method (RFC 10008): same filter DSL as the test
     * above, but carried in the request body rather than the URL.
     *
     * @return void
     */
    public function testListCanBeFilteredByNameViaTheQueryHttpMethod(): void
    {
        Widget::create([ 'name' => 'Findable Widget' ]);
        Widget::create([ 'name' => 'Other' ]);

        $response = $this->json('QUERY', 'widgets', [
            'filters' => 'name:lk(Findable)'
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Findable Widget');
    }

    /**
     * A present-but-empty ?sort= (e.g. '?sort=' or '?sort=,,,') left
     * 'query.order_by' unset while count() was still called on it -
     * a TypeError in PHP 8+.
     *
     * @return void
     */
    public function testListDoesNotCrashOnAnEmptySortParameter(): void
    {
        Widget::create([ 'name' => 'Solo' ]);

        $response = $this->getJson('widgets?sort=');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    /**
     * Two sequential requests in the same test method deliberately:
     * Laravel caches a Route's resolved controller instance, so both
     * requests hit the same repository instance. A filtered request's
     * conditions must not carry over into the next, unfiltered one.
     *
     * @return void
     */
    public function testFilteringOneRequestDoesNotLeakIntoTheNextUnfilteredOne(): void
    {
        Widget::create([ 'name' => 'Match' ]);
        Widget::create([ 'name' => 'Match Too' ]);
        Widget::create([ 'name' => 'Other' ]);

        $this->getJson('widgets?filters=name:lk(Match)')
            ->assertJsonCount(2, 'data');

        // If the old filter leaked into this unfiltered request, this
        // would wrongly still come back as 2 instead of all 3.
        $this->getJson('widgets')
            ->assertJsonCount(3, 'data');
    }
}
