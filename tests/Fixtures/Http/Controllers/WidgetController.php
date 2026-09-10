<?php
/**
 * WidgetController class file - TEST FIXTURE ONLY
 *
 * PHP Version 8.1
 *
 * @category Controller
 * @package  Rivet\Tests\Fixtures\Http\Controllers
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Fixtures\Http\Controllers;

use Rivet\Http\Controllers\BaseController;
use Rivet\Tests\Fixtures\Repositories\WidgetRepository;

/**
 * WidgetController
 *
 * Passes the Repository explicitly (same reasoning as
 * WidgetRepository's own constructor: this fixture's folder depth
 * doesn't match the real Http\Controllers -> Data\Repositories
 * convention, so ns_search() wouldn't resolve it - not the concern of
 * this fixture, which exists purely to exercise BaseController's
 * generic actions over real HTTP requests).
 *
 * @category Controller
 * @package  Rivet\Tests\Fixtures\Http\Controllers
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class WidgetController extends BaseController
{
    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct(new WidgetRepository());
    }
}
