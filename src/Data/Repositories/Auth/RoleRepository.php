<?php
/**
 * RoleRepository class file
 *
 * PHP Version 8.1
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Repositories\Auth;

use Rivet\Data\Repositories\CRUD;

/**
 * RoleRepository
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class RoleRepository extends CRUD
{
    /**
     * The rows available as filters in the query
     *
     * @var array
     */
    protected $filters = [
        'id'   => 'id',
        'uid'  => 'uid',
        'name' => 'name',
        'user' => 'relation.users'
    ];

    /**
     * Call parent abstract register method.
     *
     * @inheritdoc
     * @see        parent::register()
     */
    protected function register(array $fields): bool
    {
        return $this->defaultRegister($fields);
    }
}
