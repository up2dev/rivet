<?php
/**
 * LoginValidator class file
 *
 * PHP Version 8.1
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Validators\Auth;

use Rivet\Data\Validators\Validator;

/**
 * LoginValidator
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class LoginValidator extends Validator
{
    /**
     * The set of rules of the Validator.
     *
     * @var array
     */
    protected $rules = [
        'email' => [ 'required', 'email', 'exists:users,email' ]
    ];
}
