<?php
/**
 * Permissions class file
 *
 * PHP Version 8.1
 *
 * @category Command
 * @package  Rivet\Commands
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Rivet\Data\Models\Auth\Permission;
use Rivet\Data\Models\Route;
use Rivet\Data\Repositories\Auth\PermissionRepository;

/**
 * Permissions
 *
 * @category Command
 * @package  Rivet\Commands
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class Permissions extends Command
{
    /*
    In order to call the cmd : php artisan permissions:create
    */

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create missing permissions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $routes = Route::getRoutes();
        $exceptions = config('permissions')['route_exceptions'];
        $additions = config('permissions')['additional'];
        $permissions = [];
        $repo = new PermissionRepository();

        // foreach ($exceptions as $exception) {
        //     $exception = explode(':', $exception);
        //     $exceptions[Str::after($exception[0], 'api/')] = array_key_exists(1, $exception)? Str::upper($exception[1]): '*';
        // }

        foreach ($routes as $route) {
            if (count($route['permissions']) === 0) {
                $is_exception = false;

                // if (array_key_exists(Str::after($route['uri'], 'api/'), $exceptions)) {
                //     $is_exception = true;
                // }

                foreach($exceptions as $exception){
                    $method = Str::afterLast($exception, ':');

                    if ($method === $exception) {
                        $method = '*';
                    }

                    if (
                        Str::lower(
                            Str::after(Str::beforeLast($exception, ':'), 'api/')
                        ) === Str::lower(Str::after($route['uri'], 'api/')) &&
                        ($method == '*' || in_array($method, $route['methods']))
                    ) {
                        $is_exception = true;
                    }
                }

                if (!$is_exception) {
                    if (array_key_exists($route['uid'], $permissions)) {
                        $permissions[$route['uid']]['routes'][] = [
                            'route'  => $route['uri'],
                            'method' => $route['methods'][0]
                        ];
                    } else {
                        $permissions[$route['uid']] = [
                            'uid'    => $route['uid'],
                            'routes' => [
                                [
                                    'route'  => $route['uri'],
                                    'method' => $route['methods'][0]
                                ]
                            ]
                        ];
                    }
                }
            }
        }

        foreach($additions as $addition){
            if (Permission::where('uid', '=', $addition)->count() == 0) {
                $permissions[$addition] = [ 'uid' => $addition ];
            }
        }

        if (count($permissions) === 0) {
            $this->info('No missing permission');
        }

        foreach($permissions as $permission){
            if (array_key_exists('routes', $permission)) {
                $routes = '';

                foreach ($permission['routes'] as $key => $route) {
                    if ($key > 0) {
                        $routes .= ' and ';
                    }

                    $routes .= $route['route'].':'.$route['method'];
                }

                $this->info("Create permission {$permission['uid']} for route(s) {$routes}");
            } else {
                $this->info("Create permission {$permission['uid']}");
            }

            $repo->create([
                'uid'                => $permission['uid'],
                'name'               => $permission['uid'], 
                'permission_type_id' => 1
            ]);
        }

        return self::SUCCESS;
    }
}
