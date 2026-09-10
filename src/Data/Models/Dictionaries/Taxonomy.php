<?php
/**
 * Taxonomy class file
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rivet\Data\Models\BaseModel;
use Rivet\Database\Factories\Dictionaries\TaxonomyFactory;

/**
 * Taxonomy
 *
 * @category Model
 * @package  Rivet\Data\Models\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class Taxonomy extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * The uid associated with the model log.
     *
     * @var string
     */
    public $log_uid = 'Taxonomy';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'uid', 'name', 'is_ordered' ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [ /*'values'*/ ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [ 'deleted_at' ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_ordered' => 'boolean'
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory(): Factory
    {
        return TaxonomyFactory::new();
    }

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * Get the Taxonomy's TaxonomyValues.
     *
     * @return HasMany
     */
    public function taxonomyValues(): HasMany
    {
        return $this->hasMany(TaxonomyValue::class)->without('taxonomy');
    }

    /**
     * Get the Taxonomy's TaxonomyValues.
     *
     * @return HasMany
     */
    public function values(): HasMany
    {
        return $this->taxonomyValues()->orderBy('order');
    }

    /**
     * -------------------------------------------------------------------------
     * Mutators
     * -------------------------------------------------------------------------
     */
}
