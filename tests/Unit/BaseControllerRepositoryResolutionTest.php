<?php
/**
 * BaseControllerRepositoryResolutionTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Unit
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Unit;

use Rivet\Tests\TestCase;
use Rivet\Http\Controllers\BaseController;
use Rivet\Exceptions\ClassResolutionException;

/**
 * A Controller with no matching Repository anywhere in
 * Data\Repositories - the naming convention lookup is expected to
 * fail, on purpose, for this test.
 */
class ControllerWithNoMatchingRepository extends BaseController
{
}

/**
 * BaseControllerRepositoryResolutionTest
 *
 * Before the fix, a Controller like the one above would silently get
 * $repo = null in its constructor, and only fail later - on whichever
 * CRUD-delegating action ran first - as a generic "call to a member
 * function on null", with no indication of the real cause.
 *
 * @category Test
 * @package  Rivet\Tests\Unit
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class BaseControllerRepositoryResolutionTest extends TestCase
{
    /**
     * @return void
     */
    public function testItThrowsAClearExceptionWhenNoRepositoryCanBeResolved(): void
    {
        $this->expectException(ClassResolutionException::class);
        $this->expectExceptionMessage('ControllerWithNoMatchingRepository');

        $controller = new ControllerWithNoMatchingRepository();
        $controller->list($this->app['request']);
    }
}
