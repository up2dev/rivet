
<?php
/**
 * CRONTaskValidator class file
 *
 * PHP Version 8.1
 *
 * @category Validator
 * @package  Rivet\Data\Validators
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Validators;

use Rivet\Data\Validators\Validator;

/**
 * CRONTaskValidator
 *
 * @category Validator
 * @package  Rivet\Data\Validators
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class CRONTaskValidator extends Validator
{
    /**
     * The set of rules of the Validator.
     *
     * @var array
     */
    protected $rules = [
        'version'   => [ 'required', 'string', 'unique:dbversions,version' ],
        'sqlscript' => [ 'required', 'string' ],
        'comments'  => [ 'nullable', 'string' ]
    ];
}
