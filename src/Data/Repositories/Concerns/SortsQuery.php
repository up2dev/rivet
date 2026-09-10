<?php
/**
 * SortsQuery trait file
 *
 * PHP Version 8.1
 *
 * @category Trait
 * @package  Rivet\Data\Repositories\Concerns
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Repositories\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use MongoDB\Laravel\Connection;
use Rivet\Data\Repositories\CRUD;

/**
 * Applies sort order to CRUD's query, including sorting by a field on
 * a related model (recursing through _setQueryJoin/_getRelation to
 * join the relation first).
 *
 * @category Trait
 * @package  Rivet\Data\Repositories\Concerns
 * @license  https://opensource.org/licenses/MIT MIT License
 */
trait SortsQuery
{
    /**
     * Format Query Orders.
     *
     * @param array $orders An array of orders
     *
     * @return void
     */
    private function _setQueryOrders(array $orders = []): void
    {
        $orders = empty($orders)? config('query.order_by', []): $orders;

        foreach ($orders as $order) {
            $this->_setQueryOrder($this->query, $order['attribute'], $order['order']);
        }
    }

    /**
     * Format Query Order.
     *
     * @param Builder     $query  The query to edit (by reference)
     * @param array       $target The targeted field
     * @param string      $order  The order (asc|desc)
     * @param CRUD|null   $repo   The repo (default this)
     * @param string|null $alias  The table alias (used recursively)
     *
     * @return void
     */
    private function _setQueryOrder(
        Builder &$query,
        array $target,
        string $order,
        ?CRUD $repo = null,
        ?string $alias = null
    ): void
    {
        $repo = (is_null($repo))? $this: $repo;
        $table = $repo->getTable();

        if (count($target) > 1) {
            $repo = $this->_setQueryJoin($repo->_getRelation(
                Str::camel(array_shift($target))
            ), $repo);

            $this->_setQueryOrder($query, $target, $order, $repo, $repo->getAlias());
        } else {
            $alias = $alias?? $table;

            if ($this->model->getConnection() instanceof Connection) {
                $query->orderBy($target[0], $order);
            } else {
                $query->orderBy((
                    Schema::hasColumn($table, $target[0])? "{$alias}.{$target[0]}": $target[0]
                ), $order);
            }
        }
    }
}
