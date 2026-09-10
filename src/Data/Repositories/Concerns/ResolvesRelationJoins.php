<?php
/**
 * ResolvesRelationJoins trait file
 *
 * PHP Version 8.1
 *
 * @category Trait
 * @package  Rivet\Data\Repositories\Concerns
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Repositories\Concerns;

use Exception;
use Illuminate\Support\Str;
use Rivet\Data\Repositories\CRUD;

/**
 * Discovers a model's belongsTo/hasMany/belongsToMany relations via
 * reflection, resolves the related model's repository by naming
 * convention, and builds the SQL joins needed to filter or sort by a
 * field on a related model.
 *
 * @category Trait
 * @package  Rivet\Data\Repositories\Concerns
 * @license  https://opensource.org/licenses/MIT MIT License
 */
trait ResolvesRelationJoins
{
    /**
     * Format Query Relations.
     *
     * @param array $relations An array of relations
     *
     * @return void
     */
    public function setQueryRelations(array $relations = []): void
    {
        $relations = empty($relations)? config('query.relations', []): $relations;

        foreach ($relations as $relation) {
            $this->query->with($relation);
        }
    }

    /**
     * Set the relations arrays.
     *
     * @return void
     */
    private function _setRelations(): void
    {
        $methods = $this->reflect->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (!is_null($method->getReturnType()) && method_exists($method->getReturnType(), 'getName')) {
                $name = $method->getName();
                $type = explode('\\', $method->getReturnType()->getName());
                $type = $type[count($type) - 1];

                if (in_array(Str::lower($type), [ 'belongsto', 'hasmany', 'belongstomany' ])) {
                    $method_r = ($this->reflect->newInstance())->$name();
                    $name = Str::kebab($name);

                    switch (Str::lower($type)) {
                        case 'belongsto':
                            $this->setNORelation($name, $method_r->getOwnerKeyName());
                            break;

                        case 'hasmany':
                            $this->setONRelation($name, $method_r->getForeignKeyName());
                            break;

                        case 'belongstomany':
                            // dump($name);
                            // dump($method_r->getForeignPivotKeyName());
                            // dump($method_r->getQualifiedForeignPivotKeyName());
                            // dump($method_r->getParentKeyName());
                            // dump($method_r->getQualifiedParentKeyName());
                            // dump($method_r->getRelatedKeyName());
                            // dump($method_r->getQualifiedRelatedKeyName());
                            // dump($method_r->getRelatedPivotKeyName());
                            // dump($method_r->getQualifiedRelatedPivotKeyName());
                            $this->setNNRelation($name, $method_r->getRelatedKeyName());
                            break;
                    }
                }
            }
        }
        // dump($this->getNNRelations());
        // dump($this->getONRelation());
        // dump($this->getNORelation());
        // dd($methods);
    }

    /**
     * Format Query join.
     *
     * @param array       $join  The join details (repo, pk, fk, pivot)
     * @param CRUD|null   $repo   The repo (default this)
     * @param string|null $alias The target table's alias
     *
     * @return CRUD
     */
    private function _setQueryJoin(array $join, ?CRUD $repo = null, ?string $target_alias = null): CRUD
    {
        $repo = (is_null($repo))? $this: $repo;
        $target_repo = new $join['repo']();
        $target = $target_repo->getTable();
        $table = $repo->getAlias()?? $repo->getTable();

        if (is_null($target_alias)) {
            $target_alias = in_array($join, $this->joins)? array_search($join, $this->joins): "{$target}_order";
        }

        $target_repo->setAlias($target_alias);

        if (!in_array($join, $this->joins)) {
            if (array_key_exists('pivot', $join) && !is_null($join['pivot'])) {
                $this->query->join(
                    $join['pivot']['table'],
                    "{$table}.{$join['pivot']['target_key']}",
                    "{$join['pivot']['table']}.{$join['pivot']['owner_key']}"
                );

                $table = $join['pivot']['table'];
            }

            $this->joins[$target_alias] = $join;
            $this->query->leftJoin(
                "{$target} AS {$target_alias}", "{$target_alias}.{$join['owner_key']}", "{$table}.{$join['target_key']}"
            );
        }

        return $target_repo;
    }

    /**
     * Format an attribute to get relation informations.
     *
     * @param string $attribute An attribute that is a relation
     *
     * @return array
     */
    private function _getRelation(string $attribute): array
    {
        $relation = [];

        if ($this->reflect->hasMethod($attribute)) {
            $method = $this->reflect->getMethod($attribute);
            $method_name = $method->getName();
            $type = explode('\\', $method->getReturnType()->getName());
            $type = $type[count($type) - 1];
            $relation = [];
            $method_r = (
                $this->reflect->newInstance()
            )->$method_name();
            // $repo = get_class($method_r->getRelated());
            // $repo = str_replace('Models', 'Repositories', $repo);
            // $repo .= 'Repository';
            $repo = ns_search(get_class($method_r->getRelated()), 'repository');

            if (!class_exists($repo)) {
                throw new Exception("{$repo} not found!");
            }

            switch (Str::lower($type)) {
                case 'belongsto':
                    $relation = [
                        'repo'       => $repo,
                        'owner_key'  => $method_r->getOwnerKeyName(),
                        'target_key' => $method_r->getForeignKeyName()
                    ];
                    break;

                case 'hasmany':
                    $relation = [
                        'repo'       => $repo,
                        'owner_key'  => $method_r->getForeignKeyName(),
                        'target_key' => $method_r->getLocalKeyName()
                    ];
                    break;

                case 'belongstomany':
                    $relation = [
                        'pivot'      => [
                            'table'      => $method_r->getTable(),
                            'owner_key'  => $method_r->getForeignPivotKeyName(),
                            'target_key' => $method_r->getParentKeyName()
                        ],
                        'repo'       => $repo,
                        'owner_key'  => $method_r->getRelatedKeyName(),
                        'target_key' => $method_r->getRelatedPivotKeyName()
                    ];
                    break;
            }
        }

        return $relation;
    }
}
