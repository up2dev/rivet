<?php
/**
 * Widget class file - TEST FIXTURE ONLY
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Tests\Fixtures\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Rivet\Data\Models\BaseModel;

/**
 * Widget
 *
 * Test-only fixture. 'secret_internal_flag' exists as a DB column but
 * is deliberately left out of $fillable - this is exactly the
 * configuration the mass-assignment fix in CRUD::defaultRegister()
 * protects (see MassAssignmentProtectionTest). 'category_id' is
 * likewise deliberately left out of $fillable - it's handled through
 * CRUD's relation-association branch instead (same reasoning as
 * File's media_id).
 *
 * @category Model
 * @package  Rivet\Tests\Fixtures\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class Widget extends BaseModel
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
    protected $fillable = [ 'name', 'is_active' ];

    /**
     * @var array
     */
    protected $casts = [ 'is_active' => 'boolean' ];

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WidgetCategory::class);
    }
}
