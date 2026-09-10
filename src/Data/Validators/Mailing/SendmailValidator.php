<?php
/**
 * SendmailValidator class file
 *
 * PHP Version 8.1
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Mailing
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Validators\Mailing;

use Rivet\Data\Validators\Validator;

/**
 * SendmailValidator
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Mailing
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class SendmailValidator extends Validator
{
    /**
     * The set of rules of the Validator.
     *
     * @var array
     */
    protected $rules = [
        'subject'            => [ 'required', 'string' ],
        'from.email'         => [ 'required', 'email' ],
        'from.name'          => [ 'required', 'string' ],
        'to'                 => [ 'required', 'array', 'min:1' ],
        'to.*'               => [ 'array' ],
        'to.*.email'         => [ 'required', 'email' ],
        'to.*.name'          => [ 'required', 'string' ],
        'content.template'   => [ 'required', 'string' ],
        'content.attributes' => [ 'required', 'array' ]
    ];
}
