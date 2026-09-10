<?php
/**
 * TaxonomyValue class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models\Dictionaries;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rivet\Data\Models\BaseModel;
use Rivet\Database\Factories\Dictionaries\TaxonomyValueFactory;

/**
 * TaxonomyValue
 *
 * @category Model
 * @package  Rivet\Data\Models\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TaxonomyValue extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * The uid associated with the model log.
     *
     * @var string
     */
    public $log_uid = 'TaxonomyValue';

    /**
     * The attributes that are mass assignable. 'taxonomy_id' is
     * deliberately not listed: it is resolved through CRUD's
     * relation-association pattern, not mass-assigned as a plain
     * field.
     *
     * @var array
     */
    protected $fillable = [ 'uid', 'value', 'order' ];

    /**
     * The attribute used to group orders.
     *
     * @var string
     */
    protected $order_grouped_by = 'taxonomy_id';

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [ /*'taxonomy'*/ ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    // protected $hidden = [ 'taxonomy_id', 'deleted_at' ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory(): Factory
    {
        return TaxonomyValueFactory::new();
    }

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * Get the TaxonomyValue's Taxonomy.
     *
     * @return BelongsTo
     */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(
            Taxonomy::class
        )->without('taxonomyValues')->without('values');
    }

    /**
     * -------------------------------------------------------------------------
     * Mutators
     * -------------------------------------------------------------------------
     */

    /**
     * Get the is ordered attribute.
     *
     * @return bool
     */
    public function getIsOrderedAttribute(): bool
    {
        return $this->taxonomy->is_ordered;
    }
}
