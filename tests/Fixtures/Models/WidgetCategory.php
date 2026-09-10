<?php
/**
 * WidgetCategory class file - TEST FIXTURE ONLY
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Tests\Fixtures\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Rivet\Data\Models\BaseModel;

/**
 * WidgetCategory
 *
 * Test-only fixture: exists purely so Widget can belong to something,
 * exercising CRUD's relation-based filter/sort/join logic (see
 * CrudQueryDslTest).
 *
 * @category Model
 * @package  Rivet\Tests\Fixtures\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class WidgetCategory extends BaseModel
{
    /**
     * Not logged: irrelevant to what this fixture tests.
     *
     * @var string|null
     */
    public $log_uid = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'name' ];

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * @return HasMany
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class, 'category_id');
    }
}
