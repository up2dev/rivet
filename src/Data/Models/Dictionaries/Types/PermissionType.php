<?php
/**
 * PermissionType class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models\Dictionaries\Types
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models\Dictionaries\Types;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rivet\Data\Models\Auth\Permission;
use Rivet\Data\Models\BaseModel;
use Rivet\Database\Factories\Auth\PermissionTypeFactory;

/**
 * PermissionType
 *
 * @category Model
 * @package  Rivet\Data\Models\Dictionaries\Types
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class PermissionType extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * The uid associated with the model log.
     *
     * @var string
     */
    public $log_uid = 'PermissionType';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'uid', 'name' ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [ 'deleted_at' ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory(): Factory
    {
        return PermissionTypeFactory::new();
    }

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * Get the PermissionType's Permissions.
     *
     * @return HasMany
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(
            Permission::class
        )->without('permissionType')->without('type');
    }

    /**
     * -------------------------------------------------------------------------
     * Mutators
     * -------------------------------------------------------------------------
     */
}
