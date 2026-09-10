<?php
/**
 * Role class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models\Auth;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rivet\Data\Models\BaseModel;
use Rivet\Database\Factories\Auth\RoleFactory;

/**
 * Role
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class Role extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * The uid associated with the model log.
     *
     * @var string
     */
    public $log_uid = 'Role';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'uid', 'name' ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [ /*'permissions'*/ ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [ 'pivot', 'deleted_at' ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory(): Factory
    {
        return RoleFactory::new();
    }

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * Get the Role's Users.
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('crud.user_model'), 'user_role'
        )->without('roles');
    }

    /**
     * Get the Role's Permissions.
     *
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class, 'role_permission'
        )->without('roles');
    }

    /**
     * -------------------------------------------------------------------------
     * Mutators
     * -------------------------------------------------------------------------
     */
}
