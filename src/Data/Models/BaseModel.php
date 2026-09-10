<?php
/**
 * BaseModel class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Rivet\Data\Models\Auth\Permission;

/**
 * BaseModel
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class BaseModel extends Model
{
    use BaseModelTrait, OrderTrait, LogTrait;

    /**
     * The uid associated with the model log (default: null). \
     * If null: not logged
     *
     * @var string
     */
    public $log_uid = null;

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * -------------------------------------------------------------------------
     * Mutators
     * -------------------------------------------------------------------------
     */

    public function toArray(): array
    {
        $class = Str::upper(Str::afterLast(get_class($this), '/'));
        $permissions = Permission::where('uid', 'LIKE', "{$class}_%")->get();

        foreach ($permissions as $permission) {
            $this->hidden[] = Str::camel(Str::after($permission->uid, '_'));
        }

        return parent::toArray();
    }
}
