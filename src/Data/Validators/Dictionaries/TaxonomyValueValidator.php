<?php
/**
 * TaxonomyValueValidator class file
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
 * TaxonomyValueValidator
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TaxonomyValueValidator extends Validator
{
    /**
     * The set of rules of the Validator.
     *
     * @var array
     */
    protected $rules = [
        'uid'          => [ 'string', 'required', 'unique:taxonomy_values,uid' ],
        'value'        => [ 'string', 'required' ],
        'order'        => [ 'integer', 'nullable', 'min:1' ],
        'taxonomy_uid' => [ 'string', 'required', 'exists:taxonomies,uid' ]
    ];
}
