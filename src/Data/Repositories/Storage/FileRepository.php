<?php
/**
 * FileRepository class file
 *
 * PHP Version 8.1
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Repositories\Storage;

use Rivet\Data\Repositories\CRUD;

/**
 * FileRepository
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class FileRepository extends CRUD
{
    /**
     * The rows available as filters in the query
     *
     * @var array
     */
    protected $filters = [
        'id'        => 'id',
        'name'      => 'name',
        'extension' => 'extension',
        'size'      => 'size',
        'media'     => 'relation.media'
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
