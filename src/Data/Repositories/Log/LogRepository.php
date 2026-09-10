<?php
/**
 * LogRepository class file
 *
 * PHP Version 8.1
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Log
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Repositories\Log;

use Rivet\Data\Repositories\CRUD;

/**
 * LogRepository
 *
 * @category Repository
 * @package  Rivet\Data\Repositories\Log
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class LogRepository extends CRUD
{
    /**
     * The rows available as filters in the query
     *
     * @var array
     */
    protected $filters = [
        'id'         => '_id',
        'process'    => 'process',
        'source'     => 'source',
        'code'       => 'code',
        'data'       => 'data',
        'created_at' => 'created_at'
        // 'data-is_authenticated' => 'data.is_authenticated',
        // 'data-user_id'          => 'data.user_id',
        // // Model
        // 'data-uid'              => 'data.uid',
        // 'data-table'            => 'data.table',
        // 'data-model'            => 'data.model',
        // 'data-original'         => 'data.original',
        // 'data-original-id'      => 'data.original',
        // 'data-attributes-id     => 'data.attributes',
        // // Request
        // 'data-method'           => 'data.method',
        // 'data-protocol'         => 'data.protocol',
        // 'data-host'             => 'data.host',
        // 'data-port'             => 'data.port',
        // 'data-path'             => 'data.path',
        // 'data-query_string'     => 'data.query_string',
        // 'data-anchor'           => 'data.anchor',
        // // Response
        // 'data-status'           => 'data.status',
        // 'data-reason'           => 'data.reason',
        // // Request & Response
        // 'data-headers'          => 'data.headers',
        // 'data-body'             => 'data.body'
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
