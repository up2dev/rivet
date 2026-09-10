<?php
/**
 * Route class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Commands
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models;

use Illuminate\Support\Facades\Route as RouteBase;
use Illuminate\Support\Str;
use Rivet\Data\Models\Auth\Permission;

/**
 * Route
 *
 * @category Model
 * @package  Rivet\Commands
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class Route
{
    /**
     * List the app's API routes with their registered permissions.
     *
     * @return array
     */
    public static function getRoutes(): array
    {
        $out = [];
        $routes = RouteBase::getRoutes();

        foreach ($routes AS $route) {
            if ($route->uri != 'scripts/droits' && Str::startsWith($route->uri, 'api/')) {
                $ligne = [
                    'uri'         => $route->uri,
                    'methods'     => $route->methods,
                    'permissions' => [],
                    'uid'         => ra_to_uid($route)
                ];

                $ligne['uid'] = in_array('GET', $route->methods)? Str::beforeLast($ligne['uid'], '_'): $ligne['uid'];
                $permissions = Permission::where('uid', '=', $ligne['uid'])->get();

                if ($permissions->count() > 0) {
                    foreach($permissions AS $permission){
                        $ligne['permissions'][] = [
                            'id' => $permission->id, 'uid' => $permission->uid
                        ];
                    }
                }

                $out[] = $ligne;
            }
        }

        return $out;
    }
}
