<?php
/**
 * TaxonomyRepository class file
 *
 * PHP Version 8.1
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Repositories\Dictionaries;

use Rivet\Data\Repositories\CRUD;

/**
 * TaxonomyRepository
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TaxonomyRepository extends CRUD
{
    /**
     * The rows available as filters in the query
     *
     * @var array
     */
    protected $filters = [
        'id'   => 'id',
        'uid'  => 'uid',
        'name' => 'name'
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
