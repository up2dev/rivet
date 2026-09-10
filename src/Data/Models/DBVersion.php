<?php
/**
 * DBVersion class file
 *
 * PHP Version 8.1
 *
 * @category Trait
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models;

use Rivet\Data\Models\BaseModel;

/**
 * DBVersion
 *
 * @category Trait
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class DBVersion extends BaseModel
{
    use DBVersionTrait;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dbversions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'version', 'sqlscript', 'comments' ];

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
}
