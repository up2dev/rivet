<?php
/**
 * LogValidator class file
 *
 * PHP Version 8.1
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Log
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Validators\Log;

use Rivet\Data\Validators\Validator;

/**
 * LogValidator
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Log
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class LogValidator extends Validator
{
    /**
     * The set of rules of the Validator.
     *
     * @var array
     */
    protected $rules = [
        'process' => [ 'string', 'required' ],
        'source' => [ 'string', 'required' ],
        'code' => [ 'string', 'required' ],
        'data' => [ 'array', 'required' ]
    ];
}
