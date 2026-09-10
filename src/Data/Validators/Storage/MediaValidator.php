<?php
/**
 * MediaValidator class file
 *
 * PHP Version 8.1
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Validators\Storage;

use Rivet\Data\Validators\Validator;

/**
 * MediaValidator
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class MediaValidator extends Validator
{
    /**
     * The set of rules of the Validator.
     *
     * @var array
     */
    protected $rules = [
        'uid'        => [ 'required', 'string', 'unique:media,uid' ],
        'name'       => [ 'required', 'string' ],
        'comments'   => [ 'nullable', 'string' ],
        'mimetype'   => [ 'required', 'string' ],
        'max_chunk'  => [ 'required', 'integer', 'min:1', 'max:32767' ],
        'min_width'  => [ 'nullable', 'integer', 'min:1', 'max:32767' ],
        'max_width'  => [ 'nullable', 'integer', 'min:1', 'max:32767' ],
        'min_height' => [ 'nullable', 'integer', 'min:1', 'max:32767' ],
        'max_height' => [ 'nullable', 'integer', 'min:1', 'max:32767' ]
    ];
}
