<?php
/**
 * PasswordRenewValidator class file
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
 * PasswordRenewValidator
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class PasswordRenewValidator extends Validator
{
    /**
     * The set of rules of the Validator.
     *
     * @var array
     */
    protected $rules = [
        'password' => [ 'required', 'current_password:sanctum' ],
        'new_password' => [ 'required', 'string', 'confirmed' ]
    ];
}
