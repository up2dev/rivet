<?php
/**
 * TaxonomyValidator class file
 *
 * PHP Version 8.1
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Validators\Dictionaries;

use Rivet\Data\Validators\Validator;

/**
 * TaxonomyValidator
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TaxonomyValidator extends Validator
{
    /**
     * The set of rules of the Validator.
     *
     * @var array
     */
    protected $rules = [
        'uid'        => [ 'required', 'string', 'unique:taxonomies,uid' ],
        'name'       => [ 'required', 'string' ],
        'is_ordered' => [ 'nullable', 'boolean' ]
    ];
}
