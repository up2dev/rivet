<?php
/**
 * CRUD class file
 *
 * PHP Version 8.1
 *
 * @category Repository
 * @package  Rivet\Data\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Repositories;

use Exception;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MongoDB\Laravel\Connection;
use Rivet\Data\Models\InheritanceTrait;
use Rivet\Exceptions\ClassResolutionException;
use Rivet\Data\Repositories\Concerns\SortsQuery;
use Rivet\Data\Repositories\Concerns\BuildsFilterConditions;
use Rivet\Data\Repositories\Concerns\ResolvesRelationJoins;
use Rivet\Data\Repositories\Concerns\ScopesQueryToUser;
use Rivet\Data\QueryContext;

/**
 * Generic CRUD repository: model resolution, the CRUD verbs
 * (all/read/create/massCreate/update/massUpdate/delete/massDelete),
 * mass-assignment registration, and NN/ON/NO relation bookkeeping.
 * Filtering, sorting, relation joins, and per-user scoping are
 * provided by the traits below.
 *
 * @category Repository
 * @package  Rivet\Data\Repositories
 * @license  https://opensource.org/licenses/MIT MIT License
 */
abstract class CRUD
{
    use SortsQuery;
    use BuildsFilterConditions;
    use ResolvesRelationJoins;
    use ScopesQueryToUser;

    /**
     * Association set relation => query method prefix
     *
     * @var array
     */
    // $bitwise = [ 'n' => 'AND', 'u' => 'OR'/*, '\\' => 'XOR'*/ ];
    private const PREFIXES = [ 'n' => 'where', 'u' => 'orWhere' ];

    /**
     * Association consise operator => comparator
     *
     * @var array
     */
    private const OPERATORS = [
        'eq' => '=', 'neq' => '<>', 'gt' => '>', 'lt' => '<',
        'gte' => '>=', 'lte' => '<=', 'lk' => 'LIKE', 'nlk' => 'NOT LIKE',
        'ilk' => 'LIKE', 'nilk' => 'NOT LIKE'
    ];

    /**
     * The namespace of the Model to use
     *
     * @var string
     */
    protected $model_class = '';

    /**
     * The Model retrived by "CRU" methods
     *
     * @var Model
     */
    protected $model = null;

    /**
     * The status of the Model on register
     *
     * @var boolean
     */
    protected $is_saved = false;

    /**
     * The Table associated to the model
     *
     * @var string
     */
    protected $table = null;

    /**
     * The Collection retrived by "all" method
     *
     * @var Collection
     */
    protected $collection = null;

    /**
     * The Paginator retrived by "all" method if limit <> 0
     *
     * @var Paginator
     */
    protected $paginator = null;

    /**
     * The query used by "all" method
     *
     * @var Builder
     */
    protected $query = null;

    /**
     * The Belongs To Many relations with there key
     *
     * @var array
     */
    protected $nn_relations = [];

    /**
     * The Has Many relations with there key
     *
     * @var array
     */
    protected $on_relations = [];

    /**
     * The Belongs To relations with there key
     *
     * @var array
     */
    protected $no_relations = [];

    /**
     * The relations to reload after the register
     *
     * @var array
     */
    protected $reloads = [];

    /**
     * The columns available as filters in the query. Each entry maps
     * a client-facing key to either a column name directly
     * ('name' => 'title'), a relation ('category' => 'relation.category'),
     * or an array with 'row' (the column/relation) and an optional
     * 'forbiden' list of operators disallowed for that key.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * The joins already done
     *
     * @var array
     */
    protected $joins = [];

    /**
     * The orders available in the query
     *
     * @var array
     */
    protected $orders = [];

    /**
     * The reflection of the model
     *
     * @var \ReflectionClass
     */
    protected $reflect = null;

    /**
     * The joins alias
     *
     * @var array
     */
    protected $alias = null;

    /**
     * Set the Model we need for CRUD methods.
     *
     * @param string $model_class The Model full namespace
     */
    public function __construct(?string $model_class = null)
    {
        if (is_null($model_class)) {
            $model_class = ns_search(get_class($this), 'model');
        }

        if (is_null($model_class)) {
            throw ClassResolutionException::forTarget(static::class, 'model');
        }

        $this->model_class = $model_class;
        $this->model = new $model_class();
        $this->table = $this->model->getTable();
        $this->reflect = new \ReflectionClass($this->model_class);
        $this->query = $this->_freshQuery();

        $this->_setRelations();
    }

    /**
     * Builds a fresh, unmutated query builder for this model. Used by
     * the constructor and by every CRUD verb that touches the query
     * (setQuery/read/create/update/delete), so a repository instance
     * that handles more than one query in its lifetime never
     * accumulates where()/join()/orderBy() clauses from a prior call.
     *
     * @return Builder
     */
    private function _freshQuery(): Builder
    {
        if ($this->model->getConnection() instanceof Connection) {
            return $this->model_class::select();
        }

        return $this->model_class::selectRaw("{$this->table}.*");
    }

    /**
     * Retrive all items.
     *
     * @return Paginator|Collection|Model[]
     */
    public function all(): Paginator|Collection|array
    {
        $this->setQuery();
        $this->setQueryLimiters();
        // dd($this->query->toMql());
        // $this->query->ddRawSql();

        if (config('paginator.limit') !== 0) {
            $this->paginator = $this->query->paginate(
                config('paginator.limit'), '*', 'page', config('paginator.page')
            );
            $this->collection = $this->paginator->items();
            $this->collection = (
                $this->collection instanceof Collection?
                    $this->collection: Collection::make($this->collection)
            );

            return $this->paginator;
        }

        return $this->collection = (clone $this->query)->get();
    }

    /**
     * Retrive one item by id.
     *
     * @param int $uid The unique id of the model to retrieve
     *
     * @return Model|null
     */
    public function read(int $uid): ?Model
    {
        $this->query = $this->_freshQuery();
        $this->setQueryLimiters();
        $this->_applyContextRelations();

        return $this->model = (clone $this->query)->find($uid);
    }

    /**
     * Register a new database item.
     *
     * @param array $fields The fields to register
     *
     * @return bool
     */
    public function create(array $fields): bool
    {
        $this->model = $this->reflect->newInstanceArgs();
        $this->query = $this->_freshQuery();
        $this->setQueryLimiters($fields);
        $this->_applyContextRelations();

        return $this->register($fields);
    }

    /**
     * Register new database items.
     *
     * @param array $items A matrix of the fields to register
     *
     * @return Collection|Model[]
     */
    public function massCreate(array $items): ?Collection
    {
        $this->collection = new Collection();

        foreach ($items as $fields) {
            $this->create($fields);
            $this->collection->add($this->model);
        }

        return $this->collection;
    }

    /**
     * Modify an existing database item.
     *
     * @param array $fields The fields to register
     * @param int   $uid    The unique id of the model to modify
     *
     * @return bool
     */
    public function update(array $fields, int $uid): bool
    {
        $this->query = $this->_freshQuery();
        $this->setQueryLimiters($fields);
        $this->_applyContextRelations();
        $this->model = (clone $this->query)->find($uid);

        return $this->register($fields);
    }

    /**
     * Modify existing database items.
     *
     * @param array $items   A matrix of the fields to register
     *
     * @return bool
     */
    public function massUpdate(array $items): Collection
    {
        $this->collection = new Collection();

        foreach ($items as $fields) {
            $uid = $fields['id'];

            unset($fields['id']);
            $this->update($fields, $uid);
            $this->collection->add($this->model);
        }

        return $this->collection;
    }

    /**
     * Delete a database item.
     *
     * @param array $fields The fields to register
     * @param int   $uid    The unique id of the model to retrieve
     *
     * @return bool
     */
    public function delete(array $fields, int $uid): bool
    {
        $this->query = $this->_freshQuery();
        $this->setQueryLimiters();
        $this->_applyContextRelations();
        $this->model = (clone $this->query)->find($uid);

        return is_null($this->model)? false: $this->model->delete() === true;
    }

    /**
     * Delete database items.
     *
     * @param array|null $items A matrix of the fields to register
     *
     * @return Collection
     */
    public function massDelete(?array $items = null): Collection
    {
        if (is_null($items) || empty($items)) {
            config( [ 'paginator.limit' => 0 ] );
            $this->all();

            $items = $this->collection->map->only('id')->toArray();
        }

        $this->collection = new Collection();

        foreach ($items as $fields) {
            $uid = $fields['id'];

            unset($fields['id']);
            $this->delete($fields, $uid);
            $this->collection->add($this->model);
        }

        return $this->collection;
    }

    /**
     * The Model class string.
     *
     * @return string
     */
    public function getModelClass(): ?string
    {
        return ($this->model_class === '')? null: $this->model_class;
    }

    /**
     * The Model class string.
     *
     * @return string
     */
    public function getModelClassName(): ?string
    {
        return ($this->model_class === '')? null: explode(
            '\\', $this->model_class
        )[
            count(explode('\\', $this->model_class)) - 1
        ];
    }

    /**
     * The Table.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * The Model.
     *
     * @return Model
     */
    public function getModel(): ?Model
    {
        return $this->model;
    }

    /**
     * The Collection.
     *
     * @return Collection|null
     */
    public function getCollection(): ?Collection
    {
        return $this->collection;
    }

    /**
     * The Paginator.
     *
     * @return Paginator|null
     */
    public function getPaginator(): ?Paginator
    {
        return $this->paginator;
    }

    /**
     * The Filters.
     *
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Set Alias.
     *
     * @return string
     */
    protected function setAlias(string $alias): string
    {
        return $this->alias = $alias;
    }

    /**
     * Get Alias.
     *
     * @return string|null
     */
    protected function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * Resolve the current request's QueryContext, or a fresh empty one
     * outside the HTTP request cycle (console commands, queued jobs).
     *
     * @return QueryContext
     */
    private function _resolveQueryContext(): QueryContext
    {
        $context = new QueryContext();

        if (app()->bound('request')) {
            $context = request()->attributes->get('query_context', $context);
        }

        return $context;
    }

    /**
     * Apply the current request's eager-load relations (?with=), used
     * by setQuery() and the single-record verbs (read/create/update/
     * delete) below. Only calls setQueryRelations() when the context
     * has relations to apply - it falls back to config('query.relations')
     * on an empty array otherwise.
     *
     * @return void
     */
    private function _applyContextRelations(): void
    {
        $relations = $this->_resolveQueryContext()->relations;

        if (!empty($relations)) {
            $this->setQueryRelations($relations);
        }
    }

    /**
     * Format Query.
     *
     * @return Builder
     */
    protected function setQuery(): Builder
    {
        // Rebuilt fresh, not mutated in place, so a previous call on
        // this same instance never leaves stale where()/join()/
        // orderBy() clauses behind. $this->joins (duplicate-join
        // tracking within one build) needs the same reset.
        $this->query = $this->_freshQuery();
        $this->joins = [];

        $context = $this->_resolveQueryContext();

        if ($context->distinct) {
            $this->query->distinct();
        }

        if (!empty($context->conditions)) {
            $this->query->where(
                function ($q) use ($context) {
                    $this->_setQueryConditions($q, $context->conditions);
                }
            );
        }

        // Guarded rather than called unconditionally: _setQueryOrders()
        // falls back to config('query.*') on an empty array, which
        // would defeat the point of QueryContext.
        if (!empty($context->orderBy)) {
            $this->_setQueryOrders($context->orderBy);
        }

        $this->_applyContextRelations();

        return $this->query;
    }

    /**
     * The Belongs To Many relations and the keys used in register.
     *
     * @return array
     */
    public function setNNRelation($relation, $register_key): void
    {
        $this->nn_relations[$relation] = $register_key;
    }

    /**
     * The Belongs To Many relations ans the keys used in register.
     *
     * @return array
     */
    public function getNNRelations(): array
    {
        return $this->nn_relations;
    }

    /**
     * The Has Many relations and the keys used in register.
     *
     * @return array
     */
    public function setONRelation($relation, $register_key): void
    {
        $this->on_relations[$relation] = $register_key;
    }

    /**
     * The Has Many relations ans the keys used in register.
     *
     * @return array
     */
    public function getONRelation(): array
    {
        return $this->on_relations;
    }

    /**
     * The Belongs To relations and the keys used in register.
     *
     * @return array
     */
    public function setNORelation($relation, $register_key): void
    {
        $this->no_relations[$relation] = $register_key;
    }

    /**
     * The Belongs To relations ans the keys used in register.
     *
     * @return array
     */
    public function getNORelation(): array
    {
        return $this->no_relations;
    }

    /**
     * Register the fields in database and edit the model.
     *
     * @param array $fields The fields to save
     *
     * @return bool TRUE if success or FALSE if failed
     */
    abstract protected function register(array $fields): bool;

    /**
     * Register the fields in database and edit the model.
     *
     * @param array $fields The fields to save
     *
     * @return bool TRUE if success or FALSE if failed
     */
    protected function defaultRegister(array $fields): bool
    {
        $this->reloads = [];
        $plain_fields = [];

        foreach ($fields as $field => $value) {
            if (preg_match('/^(?:.*?)_u?id$/', $field)) {
                $association = explode('_', $field);
                $key = array_pop($association);
                $association = Str::camel(implode('_', $association));

                if (method_exists($this->model_class, $association)) {
                    if (is_null($value)) {
                        $this->model->$association()->dissociate();
                    } else {
                        $target = get_class(
                            $this->model->$association()->getQuery()->getModel()
                        );

                        $this->model->$association()->associate(
                            $target::firstWhere($key, $value)
                        );
                    }
                }
            } elseif (!in_array($field, array_keys($this->getNNRelations()))) {
                if (
                    $this->model->getConnection() instanceof Connection ||
                    Schema::hasColumn($this->getTable(), $field)
                ) {
                    $plain_fields[$field] = $value;
                }
            }
        }

        // Goes through Eloquent's fill(), which enforces $fillable/$guarded
        // on the Model. Earlier versions wrote $this->model->$field = $value
        // directly here for every column that existed in the table,
        // which bypassed mass-assignment protection entirely: a client
        // could set ANY existing column (is_active, role_id, ...) as long
        // as the route's Validator didn't happen to reject that field
        // name. The Validator is still the primary gate on which fields
        // reach this point, but $fillable is now a real second layer
        // instead of a no-op.
        $this->model->fill($plain_fields);
        // if put and not patch => foreach attributes that are not in $fields = null ???

        $this->is_saved = $this->model->save();

        $this->_sync($fields);

        // Can't be called in InheritanceTrait because it also handel relations
        if (in_array(
            InheritanceTrait::class, array_keys($this->reflect->getTraits())
        )) {
            $this->model->syncInherit();
        }

        return $this->is_saved;
    }

    /**
     * Register Belongs to Many fields associated with the model in database.
     *
     * @param array $fields The fields to save
     *
     * @return void
     */
    private function _sync(array $fields): void
    {
        foreach ($this->getNNRelations() as $nnr => $field) {
            if (array_key_exists($nnr, $fields)) {
                array_push($this->reloads, $association = Str::camel($nnr));
                $target = get_class(
                    $this->model->$association()->getQuery()->getModel()
                );
                $values = [];

                foreach ($fields[$nnr] as $realtion) {
                    $id = ($target::firstWhere($field, $realtion[$field]))->id;
                    unset($realtion[$field]);

                    if (empty($realtion)) {
                        array_push($values, $id);
                    } else {
                        $values[$id] = $realtion;
                    }
                }

                // sync() replaces the full pivot set; switching to
                // syncWithoutDetaching() would make this additive
                // instead - a deliberate API choice, not a bug.
                $this->model->$association()->sync($values);
            }
        }

        $this->model->load($this->reloads);
    }
}
