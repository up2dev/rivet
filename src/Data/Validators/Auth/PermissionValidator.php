<?php
/**
 * PermissionValidator class file
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
 * PermissionValidator
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class PermissionValidator extends Validator
{
    /**
     * The set of rules of the Validator.
     *
     * @var array
     */
    protected $rules = [
        'uid'                => [ 'required', 'unique:permissions,uid' ],
        'name'               => [ 'required' ],
        'permission_type_id' => [ 'required', 'exists:permission_types,id' ]
    ];
}
