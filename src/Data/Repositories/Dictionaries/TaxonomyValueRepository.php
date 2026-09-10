<?php
/**
 * TaxonomyValueRepository class file
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
 * TaxonomyValueRepository
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Dictionaries
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class TaxonomyValueRepository extends CRUD
{
    /**
     * The rows available as filters in the query
     *
     * @var array
     */
    protected $filters = [
        'id'       => 'id',
        'uid'      => 'uid',
        'value'    => 'value',
        'taxonomy' => 'relation.taxonomy'
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
