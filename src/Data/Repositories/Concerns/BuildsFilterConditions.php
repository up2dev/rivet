<?php
/**
 * BuildsFilterConditions trait file
 *
 * PHP Version 8.1
 *
 * @category Trait
 * @package  Rivet\Data\Repositories\Concerns
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Repositories\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use MongoDB\Laravel\Connection;
use Rivet\Data\Repositories\CRUD;
use Rivet\Exceptions\InvalidQueryFilterException;

/**
 * Builds the WHERE clauses for CRUD's filter DSL (?filters=...):
 * parsing conditions into query methods, resolving operators, and
 * validating filter keys against the repository's declared filters.
 * Composed into CRUD via `use`, giving it access to CRUD's
 * self::PREFIXES/self::OPERATORS constants and to the relation/join
 * methods in ResolvesRelationJoins.
 *
 * @category Trait
 * @package  Rivet\Data\Repositories\Concerns
 * @license  https://opensource.org/licenses/MIT MIT License
 */
trait BuildsFilterConditions
{
    /**
     * Format Query conditions recursively.
     *
     * @param Builder $query      The query to edit (by reference)
     * @param array   $conditions An nested array of conditions
     *
     * @return void
     *
     * @throws InvalidQueryFilterException If a condition's bitwise
     *                                     operator is unknown
     */
    private function _setQueryConditions(Builder &$query, array $conditions): void
    {
        foreach ($conditions as $cond) {
            if (!array_key_exists($cond['bitwise'], self::PREFIXES)) {
                throw InvalidQueryFilterException::unknownBitwise($cond['bitwise']);
            }

            $method = self::PREFIXES[$cond['bitwise']];

            if (array_key_exists('conditions', $cond)) {
                $query->$method(function ($q) use ($cond) {
                    $this->_setQueryConditions($q, $cond['conditions']);
                });
            } else {
                if (
                    $this->model->getConnection() instanceof Connection &&
                    Str::endsWith($cond['target'], '_at')
                ) {
                    // Only accepts a bare YYYY-MM-DD date, not a full
                    // datetime.
                    if (
                        preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $cond['value']) &&
                        ($cond['operator'] === 'lte' || $cond['operator'] === 'gt')
                    ) {
                        $cond['value'] .= ' 23:59:59';
                    }

                    $cond['value'] = new \Carbon\Carbon($cond['value']);
                }

                $this->_setQueryCondition(
                    $query, $cond['bitwise'], $cond['target'], $cond['operator'], $cond['value']
                );
            }
        }
    }

    /**
     * Format Query condition.
     *
     * @param Builder     $query    The query to edit (by reference)
     * @param string      $bitwise  The bitwise operator
     * @param string      $target   The targeted field
     * @param string      $operator The condition operator
     * @param mixed       $value    The value to compare
     * @param CRUD|null   $repo     The repo (default this)
     * @param string|null $alias    The table alias (used recursively)
     *
     * @return void
     */
    private function _setQueryCondition(
        Builder &$query,
        string $bitwise,
        string $target,
        string $operator,
        mixed $value,
        ?CRUD $repo = null,
        ?string $alias = null
    ): void
    {
        $repo = (is_null($repo))? $this: $repo;
        $table = $repo->getTable();
        $target = explode('.', $target);
        $params = $repo->_getFilterRaw($target[0], $operator, $repo->getFilters());


        if (is_array($params)) {
            $alias = $target[0];
            array_shift($target);

            $repo = $this->_setQueryJoin($params, $repo, $alias);

            $this->_setQueryCondition(
                $query, $bitwise, join('.', $target), $operator, $value, $repo, $repo->getAlias()
            );
        } else {
            $alias = $alias?? $table;
            $params = (
                $this->model->getConnection() instanceof Connection ||
                !Schema::hasColumn($table, $params)
            )? $params: "{$alias}.{$params}";
            $params = [ $params ];

            call_user_func_array([ $query, $this->_getMethod($bitwise, $operator, $value, $params) ], $params);
        }
    }

    /**
     * Transform an operator into a query method.
     *
     * @param string $bitwise  The bitwise operator ('n' or 'u')
     * @param string $operator The search operator (lk, eq, btw...)
     * @param mixed  $value    The searched value
     * @param array  $params   The search parameters (by reference)
     *
     * @return string
     */
    private function _getMethod(
        string $bitwise, string $operator, mixed $value, array &$params
    ): string
    {
        $method = self::PREFIXES[$bitwise];
        $method .= $this->_methodNegate($operator);
        $method .= $this->_methodSuffix($operator, $value, $params);

        return $method;
    }

    /**
     * Transform an operator and extract a suffix if negate (whereNot).
     *
     * @param string $operator The search operator (lk, eq, btw...) (by reference)
     *
     * @return string
     */
    private function _methodNegate(string &$operator): string
    {
        $suffix = '';

        if (in_array($operator, [ 'nbtw', 'nin', 'nn' ])) {
            $suffix .= 'Not';
            $operator = substr($operator, 1);
        }

        return $suffix;
    }

    /**
     * Transform an operator into a query method suffix.
     *
     * @param string $operator The search operator (lk, eq, btw...)
     * @param mixed  $value    The searched value
     * @param array  $params   The search parameters (by reference)
     *
     * @return string
     *
     * @throws InvalidQueryFilterException If the value or operator is
     *                                     invalid
     */
    private function _methodSuffix(
        string $operator, mixed $value, array &$params
    ): string
    {
        $suffix = '';

        switch ($operator) {
            case 'btw':
                $suffix .= 'Between';
                $params[1] = explode(',', $value);

                if (count($params[1]) !== 2) {
                    throw InvalidQueryFilterException::invalidBetweenValue($value);
                }
                break;

            case 'in':
                $suffix .= 'In';
                $params[1] = explode(',', $value);

                // count() < 1 here would be unreachable: explode()
                // always returns at least one element, even for an
                // empty string. Check the value itself instead.
                if ($value === '') {
                    throw InvalidQueryFilterException::invalidInValue($value);
                }
                break;

            case 'n':
                $suffix .= 'Null';
                break;

            case 'ist':
            case 'isf':
                $params[1] = '=';
                $params[2] = ($operator === 'ist');
                break;

            default:
                if (!array_key_exists($operator, self::OPERATORS)) {
                    throw InvalidQueryFilterException::unknownOperator($operator);
                }

                if (
                    $this->model->getConnection() instanceof Connection &&
                    is_string($value) &&
                    $value == intval($value)
                ) {
                    $value = intval($value);
                }

                $params[1] = self::OPERATORS[$operator];
                // $value is not validated against the target column's
                // type (e.g. a date column would accept any string as
                // an 'eq' value) - would need a validation ruleset per
                // filter key.
                $params[2] = $value;

                // 'lk'/'nlk'/'ilk'/'nilk' need SQL wildcards or LIKE
                // behaves as an exact match.
                if (in_array($operator, [ 'lk', 'nlk', 'ilk', 'nilk' ])) {
                    $params[2] = "%{$params[2]}%";
                }

                if ($operator === 'ilk') {
                    $params[0] = DB::raw("LOWER({$params[0]})");
                    $params[2] = strtolower($params[2]);
                }
                break;
        }

        return $suffix;
    }

    /**
     * Check a filter validity. Return the corresponding column.
     *
     * @param string     $key      The filter key in filters array
     * @param string     $operator The search operator (lk, eq, btw...)
     * @param array|null $filters  The filters (defaults to $this->filters)
     *
     * @return mixed
     *
     * @throws InvalidQueryFilterException If the key is unknown, or
     *                                     the operator is forbidden
     *                                     for it
     */
    private function _getFilterRaw(
        string $key, string $operator, ?array $filters = null
    ): mixed {
        $filters = is_null($filters)? $this->filters: $filters;
        $filter = null;

        if (
            $this->model->getConnection() instanceof Connection &&
            Str::contains($key, '-')
        ) {
            $tmp_exploded_key = explode('-', $key);
            $key = $tmp_exploded_key[0];
        }

        if (!array_key_exists($key, $filters)) {
            throw InvalidQueryFilterException::unknownFilterKey($key);
        }

        if (
            is_array($filters[$key]) &&
            array_key_exists('forbiden', $filters[$key]) &&
            in_array($operator, $filters[$key]['forbiden'])
        ) {
            throw InvalidQueryFilterException::forbiddenOperator($key, $operator);
        }

        if (is_array($filters[$key])) {
            $filter = $filters[$key]['row'];
        } else {
            $filter = $filters[$key];

            if (explode('.', $filters[$key])[0] === 'relation') {
                $filter = $this->_getRelation(Str::camel(
                    explode('.', $filters[$key])[1]
                ));
            }
        }

        if (
            $this->model->getConnection() instanceof Connection &&
            isset($tmp_exploded_key) &&
            is_array($tmp_exploded_key)
        ) {
            $filter = implode('.', $tmp_exploded_key);
        }

        return $filter;
    }
}

