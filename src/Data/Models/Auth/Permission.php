<?php
/**
 * Permission class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models\Auth;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Rivet\Data\Models\BaseModel;
use Rivet\Data\Models\Dictionaries\Types\PermissionType;
use Rivet\Database\Factories\Auth\PermissionFactory;

/**
 * Permission
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class Permission extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * The uid associated with the model log.
     *
     * @var string
     */
    public $log_uid = 'Permission';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'uid', 'name', 'permission_type_id' ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [ /*'type'*/ ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [ 'routes' ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [ 'pivot', 'permission_type_id', 'deleted_at' ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory(): Factory
    {
        return PermissionFactory::new();
    }

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * Get the Permission's Roles.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class, 'role_permission'
        )->without('roles');
    }

    /**
     * Get the Permission's Type.
     *
     * @return BelongsTo
     */
    public function permissionType(): BelongsTo
    {
        return $this->belongsTo(PermissionType::class)->without('permissions');
    }

    /**
     * Get the Permission's Type.
     *
     * @return BelongsTo
     */
    public function type(): BelongsTo
    {
        return $this->permissionType();
    }

    /**
     * -------------------------------------------------------------------------
     * Mutators
     * -------------------------------------------------------------------------
     */

    /**
     * Get the Permission's routes.
     *
     * @return Collection
     */
    public function getRoutesAttribute(): Collection
    {
        $routes = new Collection();
        $attrs = $this->attributes;
        $controller = Str::after($attrs['uid'], '_');
        $controller = Str::contains($controller, '_')? '_' . Str::after(
            $controller, '_'
        ): '';
        $controller = Str::beforeLast($attrs['uid'], $controller);
        $has_method = false;

        foreach (Route::getRoutes() as $route) {
            if (
                isset($route->action['middleware']) &&
                in_array('lpfauth:sanctum', $route->action['middleware'])
            ) {
                $uid = ra_to_uid($route);

                if (Str::startsWith($uid, "{$controller}_")) {
                    $has_method = !$has_method? Str::contains(
                        $attrs['uid'], Str::after($uid, "{$controller}_")
                    ): $has_method;

                    if ($has_method || Permission::where(
                        'uid', 'LIKE', "{$uid}%"
                    )->where(
                        'id', '<>', $attrs['id']
                    )->where(
                        'permission_type_id', PermissionType::firstWhere('uid', 'ENDPOINT')->id
                    )->count() === 0) {
                        $routes->add([
                            'uid'    => $uid,
                            'uri'    => $route->uri,
                            'method' => $route->methods
                        ]);
                    }
                }
            // } else {
            //     if (Str::startsWith($route->uri, 'api')) {
            //         $routes->add([
            //             'uid'    => null,
            //             'uri'    => $route->uri,
            //             'method' => $route->methods
            //         ]);
            //     }
            }
        }

        if ($has_method) {
            $routes = $routes->filter(function ($route) use ($attrs) {
                return Str::startsWith($attrs['uid'], $route['uid']);
            })->values();
        }

        return $routes;
    }
}
