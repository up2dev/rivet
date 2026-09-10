<?php
/**
 * CRONTask class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models;

use Rivet\Data\Models\BaseModel;

/**
 * CRONTask
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class CRONTask extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crontasks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uid', 'command', 'minute', 'hour', 'day', 'month', 'year'
    ];

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
