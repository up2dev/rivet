<?php
/**
 * RoleValidator class file
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
 * RoleValidator
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class RoleValidator extends Validator
{
    /**
     * The set of rules of the Validator.
     *
     * @var array
     */
    protected $rules = [
        'uid'               => [ 'required', 'string', 'unique:roles,uid' ],
        'name'              => [ 'required', 'string' ],
        'permissions'       => [ 'array' ],
        'permissions.*'     => [ 'array' ],
        'permissions.*.uid' => [ 'required', 'exists:permissions,uid', 'distinct' ]
    ];

    protected $edit_rules = [
        'name'              => [ 'required', 'string' ],
        'permissions'       => [ 'array' ],
        'permissions.*'     => [ 'array' ],
        'permissions.*.uid' => [ 'exists:permissions,uid', 'distinct' ]
    ];
}
