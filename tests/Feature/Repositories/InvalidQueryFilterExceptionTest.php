<?php
/**
 * InvalidQueryFilterExceptionTest class file
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
use Rivet\Exceptions\InvalidQueryFilterException;

/**
 * Covers the filter DSL's input validation: an unknown filter key, an
 * unknown operator, or a malformed 'in'/'btw' value now throws
 * InvalidQueryFilterException instead of silently building an
 * incorrect or empty query.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class InvalidQueryFilterExceptionTest extends TestCase
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
    public function testUnknownFilterKeyThrows(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(InvalidQueryFilterException::class);

        $this->getJson('widgets?filters=nonexistent:eq(foo)');
    }

    /**
     * @return void
     */
    public function testUnknownOperatorThrows(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(InvalidQueryFilterException::class);

        $this->getJson('widgets?filters=name:bogus(foo)');
    }

    /**
     * @return void
     */
    public function testMalformedBetweenValueThrows(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(InvalidQueryFilterException::class);

        $this->getJson('widgets?filters=id:btw(1)');
    }
}
