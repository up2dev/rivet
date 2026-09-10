<?php
/**
 * Media class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models\Storage;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Rivet\Data\Models\BaseModel;
use Rivet\Database\Factories\Storage\MediaFactory;

/**
 * Media
 *
 * @category Model
 * @package  Rivet\Data\Models\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class Media extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * The uid associated with the model log.
     *
     * @var string
     */
    public $log_uid = 'Media';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uid', 'name', 'comments', 'max_chunk', 'mimetype',
        'min_width', 'max_width', 'min_height', 'max_height'
    ];

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
        return MediaFactory::new();
    }

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * Get the Media's Files.
     *
     * @return HasMany
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class)->without('media');
    }

    /**
     * -------------------------------------------------------------------------
     * Mutators
     * -------------------------------------------------------------------------
     */
}
