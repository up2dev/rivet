<?php
/**
 * UserRepository class file
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
 * UserRepository
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class UserRepository extends CRUD
{
    /**
     * The rows available as filters in the query
     *
     * @var array
     */
    protected $filters = [
        'id'        => 'id',
        'login'     => 'login',
        'email'     => 'email',
        'role'      => 'relation.roles',
        'is_active' => 'is_active'
    ];

    /**
     * Set parent CRUD.
     */
    public function __construct()
    {
        parent::__construct(config('crud.user_model'));
    }

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
