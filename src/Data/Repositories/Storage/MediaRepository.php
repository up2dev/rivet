<?php
/**
 * MediaRepository class file
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
 * MediaRepository
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class MediaRepository extends CRUD
{
    /**
     * The rows available as filters in the query
     *
     * @var array
     */
    protected $filters = [
        'id'       => 'id',
        'uid'      => 'uid',
        'name'     => 'name',
        'mimetype' => 'mimetype'
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
