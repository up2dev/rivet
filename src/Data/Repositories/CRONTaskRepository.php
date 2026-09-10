<?php
/**
 * CRONTaskRepository class file
 *
 * PHP Version 8.1
 *
 * @category Repository
 * @package  Rivet\Data\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Repositories;

use Rivet\Data\Repositories\CRUD;

/**
 * CRONTaskRepository
 *
 * @category Repository
 * @package  Rivet\Data\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class CRONTaskRepository extends CRUD
{
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
