<?php
/**
 * QueryStringToConfig class file
 *
 * PHP Version 8.1
 *
 * @category Middleware
 * @package  Rivet\Http\Middleware
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Rivet\Data\QueryContext;

/**
 * Parses ?filters=/?sort=/?with=/?page=/?limit=/?distinct= (or the
 * equivalent QUERY-method body) into a QueryContext for the current
 * request, and mirrors the same values into config('query.*')/
 * config('paginator.*') for backward compatibility.
 *
 * @category Middleware
 * @package  Rivet\Http\Middleware
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class QueryStringToConfig
{
    /**
     * Built fresh on every request - never carried over from a
     * previous one, unlike config('query.*').
     *
     * @var QueryContext
     */
    private QueryContext $context;

    /**
     * Handle an incoming request.
     *
     * @param Request  $request The request to validate
     * @param \Closure $next    The controller method passed in routes
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Attached to this request specifically, before any parsing
        // below - CRUD::setQuery() reads it via $request->attributes.
        $this->context = new QueryContext();
        $request->attributes->set('query_context', $this->context);

        // input() merges the URL query string with the request body,
        // so this also supports the HTTP QUERY method (RFC 10008),
        // whose parameters arrive in the body rather than the URL.
        if (!is_null($request->input('page'))) {
            config(
                [ 'paginator.page' => intval($request->input('page')) ]
            );
        }

        if (!is_null($request->input('limit'))) {
            config(
                [ 'paginator.limit' => intval($request->input('limit')) ]
            );
        }

        if (!is_null($request->input('sort'))) {
            $this->_formatOrderBy($request->input('sort'));
        }

        if (!is_null($request->input('filters'))) {
            $this->_formatFilters($request->input('filters'));
        }

        if (!is_null($request->input('distinct'))) {
            $this->context->distinct = (bool) intval($request->input('distinct'));

            config(
                [ 'query.distinct' => intval($request->input('distinct')) ]
            );
        }

        if (!is_null($request->input('with'))) {
            $this->_formatRelations($request->input('with'));
        }

        return $next($request);
    }

    /**
     * Extract the order by raw string and add it to global config
     *
     * @param string $qstring The sort query param
     *
     * @return void
     */
    private function _formatOrderBy(string $qstring): void
    {
        $orders = explode(',', $qstring);

        foreach ($orders as $key => $order) {
            if ($order !== '') {
                $order = explode('.', $order);
                $suffix = 'asc';

                if (in_array(strtolower($order[count($order) - 1]), [ 'asc', 'desc' ])) {
                    $suffix = strtolower($order[count($order) - 1]);
                    unset($order[count($order) - 1]);
                }

                $this->context->orderBy[$key] = [
                    'attribute' => $order, 'order' => $suffix
                ];

                config(
                    [
                        "query.order_by.{$key}" => [
                            'attribute' => $order, 'order' => $suffix
                        ]
                    ]
                );
            }
        }

        // Explicit default: a present-but-empty ?sort= (e.g. '?sort='
        // or '?sort=,,,') never enters the loop above, leaving
        // 'query.order_by' unset - count(null) would throw.
        if (count(config('query.order_by', [])) > 0) {
            config([ 'query.sort' => $qstring ]);
        }
    }

    /**
     * Extract the filters raw string and add it to global config
     *
     * @param string $qstring The filters query param
     *
     * @return void
     */
    private function _formatFilters(string $qstring): void
    {
        $this->context->conditions = $this->_filtersParser($qstring);

        config(
            [ 'query.conditions' => $this->context->conditions ]
        );

        if (count(config('query.conditions')) > 0) {
            config([ 'query.sort' => $qstring ]);
        }
    }

    /**
     * Change a filters string into nested arrays of conditions
     *
     * @param string $qstring The filters query to parse
     *
     * @return void
     */
    private function _filtersParser(string $qstring): array
    {
        // Dense, bracket/regex-based parsing - see _getBraketsPositions()
        // and _conditionsParser() below for the two halves of it.
        $conditions = [];
        $brakets = $this->_getBraketsPositions($qstring);

        if (count($brakets) === 0) {
            $conditions = array_merge(
                $conditions, $this->_conditionsParser($qstring)
            );
        }

        foreach ($brakets as $key => $br) {
            $start = ($key === 0)? 0: $brakets[$key - 1]['closing'] + 1;
            $close = $br['opening'] - 3;

            if ($close - $start > 0) {
                $conditions = array_merge(
                    $conditions,
                    $this->_conditionsParser(
                        substr($qstring, $start, $close)
                    )
                );
            }

            $start = $br['opening'] + 1;
            $close = $br['closing'] - $br['opening'] - 1;

            $conditions[count($conditions)] = [
                'bitwise'    => (
                    $br['opening'] === 0
                )? 'n': $qstring[$br['opening'] - 2],
                'conditions' => $this->_filtersParser(
                    substr($qstring, $start, $close)
                )
            ];

            if (
                $key === count($brakets) - 1 &&
                $br['closing'] + 1 !== strlen($qstring)
            ) {
                $conditions = array_merge(
                    $conditions,
                    $this->_conditionsParser(
                        substr($qstring, $br['closing'] + 1)
                    )
                );
            }
        }

        return $conditions;
    }

    /**
     * Retrive the opening and corresponding closing brakets in a string
     * without considering nested ones.
     *
     * @param string $str The string to analyze
     *
     * @return array
     */
    private function _getBraketsPositions(string $str): array
    {
        $opened = 0;
        $brakets = [];

        foreach (str_split($str) as $key => $char) {
            if ($char === '[') {
                $opened++;

                if ($opened === 1) {
                    $brakets[] = [ 'opening' => $key ];
                }
            }

            if ($char === ']' && $opened >= 1) {
                if ($opened === 1) {
                    $brakets[count($brakets) - 1]['closing'] = $key;
                }

                $opened--;
            }
        }

        return $brakets;
    }

    /**
     * Change a filters string into an array of conditions
     *
     * @param string $qstring The filters query to parse
     *
     * @return array
     */
    private function _conditionsParser(string $qstring): array
    {
        $filters = explode(
            '|separator|', preg_replace('/\|(u|n|\\\)\|/', '|separator|$1:', $qstring)
        );

        if ($filters[0] === '') {
            array_shift($filters);
        }

        foreach ($filters as $key => $filter) {
            $filter = explode(
                '|separator|',
                preg_replace(
                    '/(^(u)\:|^(n)\:|^)(.*?)\:(.*?)\((.*?)\)$/',
                    '$2$3|separator|$4|separator|$5($6)',
                    $filter
                )
            );
            $filter[2] = explode(
                '(', $filter[2], 2
            );

            $filters[$key] = [
                'bitwise'  => ($filter[0] === '')? 'n': $filter[0],
                'target'   => $filter[1],
                'operator' => $filter[2][0],
                'value'    => substr($filter[2][1], 0, -1)
            ];
        }

        return $filters;
    }

    /**
     * Extract the relations raw string and add it to global config
     *
     * @param string $qstring The relations to load
     *
     * @return void
     */
    private function _formatRelations(string $qstring): void
    {
        $this->context->relations = $this->_relationsParser($qstring);

        config(
            [ 'query.relations' => $this->context->relations ]
        );

        if (count(config('query.relations')) > 0) {
            config([ 'query.with' => $qstring ]);
        }
    }

    /**
     * Change a with string into array of relations
     *
     * @param string $qstring The with query to parse
     *
     * @return void
     */
    private function _relationsParser(string $qstring): array
    {
        return explode(':', $qstring);
    }
}
