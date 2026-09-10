<?php
/**
 * QueryContext class file
 *
 * PHP Version 8.1
 *
 * @category Data
 * @package  Rivet\Data
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data;

/**
 * Request-scoped filter/sort/relation state, built fresh by
 * QueryStringToConfig on every request and attached to that request's
 * attributes. Replaces reading global config('query.*') state
 * directly from CRUD, which could otherwise leak between requests
 * sharing a repository instance (e.g. a cached Route controller) or
 * under a persistent-worker deployment.
 *
 * @category Data
 * @package  Rivet\Data
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class QueryContext
{
    /**
     * Parsed filter conditions (BuildsFilterConditions' nested shape).
     *
     * @var array
     */
    public array $conditions = [];

    /**
     * Parsed sort orders (SortsQuery's shape:
     * [['attribute' => [...], 'order' => 'asc'|'desc'], ...]).
     *
     * @var array
     */
    public array $orderBy = [];

    /**
     * Eager-load relation names for Eloquent's with().
     *
     * @var array
     */
    public array $relations = [];

    /**
     * Whether the query should be DISTINCT.
     *
     * @var bool
     */
    public bool $distinct = false;
}
