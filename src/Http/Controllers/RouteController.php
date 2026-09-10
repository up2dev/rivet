<?php
/**
 * RouteController class file
 *
 * PHP Version 8.1
 *
 * @category Controller
 * @package  Rivet\Http\Controllers
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rivet\Http\Controllers\BaseController;
use Rivet\Data\Models\Route;

/**
 * RouteController
 *
 * @category Controller
 * @package  Rivet\Http\Controllers
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class RouteController extends BaseController
{
    /**
     * Method called by the /remote_admin/routes URL in GET.
     *
     * @param Request $request The injected Request
     *
     * @return JsonResponse
     */
    public function routes(Request $request): JsonResponse
    {
        $this->setResponse(Route::getRoutes());

        return $this->response->format();
    }
}
