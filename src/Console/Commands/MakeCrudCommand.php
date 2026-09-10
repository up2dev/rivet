<?php
/**
 * MakeCrudCommand class file
 *
 * PHP Version 8.1
 *
 * @category Command
 * @package  Rivet\Console\Commands
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * MakeCrudCommand
 *
 * Scaffolds a complete Rivet CRUD entity (Model, Repository,
 * Controller, Validator, and optionally a Migration) from a table that
 * ALREADY EXISTS in the database - the common case for this project,
 * where the schema is usually designed and migrated before the PHP
 * layer is written, rather than the other way around Laravel usually
 * assumes.
 *
 * Everything is generated under the host application's own App\
 * namespace (never under Rivet\), following exactly the
 * folder layout BaseController/CRUD/DataValidate resolve by naming
 * convention (see ns_search() in src/helpers.php):
 *
 *   app/Data/Models/{Model}.php
 *   app/Data/Repositories/{Model}Repository.php
 *   app/Http/Controllers/{Model}Controller.php
 *   app/Data/Validators/{Model}Validator.php
 *   database/migrations/..._create_{table}_table.php   (with --migration)
 *
 * Usage:
 *   php artisan rivet:make:crud articles
 *   php artisan rivet:make:crud articles --model=Article
 *   php artisan rivet:make:crud articles --migration
 *   php artisan rivet:make:crud articles --force
 *
 * @category Command
 * @package  Rivet\Console\Commands
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class MakeCrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rivet:make:crud
        {table : The existing database table to scaffold from}
        {--model= : Model class name (default: studly singular of the table)}
        {--migration : Also generate a migration matching the current table}
        {--force : Overwrite files that already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scaffold a full Rivet CRUD entity '.
        '(Model, Repository, Controller, Validator) from an existing DB table';

    /**
     * Columns never treated as plain fillable attributes: primary key,
     * timestamps, soft-delete marker.
     *
     * @var string[]
     */
    private const SKIP_FIELDS = [
        'id', 'created_at', 'updated_at', 'deleted_at'
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $table = $this->argument('table');

        if (!Schema::hasTable($table)) {
            $this->error(
                "Table '{$table}' does not exist in the current ".
                'database connection.'
            );

            return self::FAILURE;
        }

        $model = $this->option('model') ?: Str::studly(Str::singular($table));
        $columns = $this->_introspect($table);
        $relations = $this->_introspectRelations($table);
        $unique_columns = $this->_introspectUniqueColumns($table);

        $this->_writeModel($model, $table, $columns, $relations);
        $this->_writeRepository($model, $columns, $relations);
        $this->_writeController($model);
        $this->_writeValidator($model, $table, $columns, $unique_columns);

        if ($this->option('migration')) {
            $this->_writeMigration($model, $table, $columns);
        }

        $this->newLine();
        $this->info("CRUD scaffolded for '{$table}' as {$model}.");

        $guessed = array_filter($relations, fn ($r) => $r['guessed']);

        if (!empty($guessed)) {
            $this->warn(
                'Relation target(s) guessed from column names only (no '.
                'foreign key metadata on this Laravel version) - review '.
                'before relying on them: '.
                implode(', ', array_column($guessed, 'column'))
            );
        }

        $this->_printRouteSnippet($model, $table);

        return self::SUCCESS;
    }

    /**
     * Read the table's columns and normalize them into a simple shape
     * this command's templates can consume, regardless of which Schema
     * introspection API the host's Laravel version exposes.
     *
     * @param string $table The table to introspect
     *
     * @return array<int, array{name: string, type: string, nullable: bool}>
     */
    private function _introspect(string $table): array
    {
        $columns = [];

        // Schema::getColumns() (rich: type + nullable + default) only
        // exists since Laravel 11. On Laravel 10 (still supported by
        // composer.json's ">=10"), fall back to getColumnListing() +
        // getColumnType() and assume nullable - the generated Validator
        // is clearly commented either way so this is reviewed by hand,
        // never trusted blindly.
        if (method_exists(Schema::getFacadeRoot(), 'getColumns')) {
            foreach (Schema::getColumns($table) as $column) {
                $type = $column['type_name'] ?? $column['type'];
                $length = null;

                // The raw 'type' string (e.g. 'varchar(255)') carries a
                // length on MySQL/Postgres, used below for a max:
                // validation rule. SQLite's introspection never carries
                // one at all - and neither does its DDL for a column
                // declared via Schema::string($name, $length): Laravel's
                // SQLite grammar drops the length entirely, storing
                // just 'varchar' (confirmed against the raw CREATE
                // TABLE statement, not assumed). _sqliteColumnLength()
                // below still checks the DDL as a fallback, in case a
                // table was created some other way (raw SQL) that does
                // preserve a length there - but for the common case
                // (Schema::string()), max: is simply not derivable on
                // SQLite and $length stays null.
                if (preg_match('/\((\d+)\)/', $column['type'] ?? '', $m)) {
                    $length = (int) $m[1];
                } else {
                    $length = $this->_sqliteColumnLength($table, $column['name']);
                }

                $columns[] = [
                    'name'     => $column['name'],
                    'type'     => $type,
                    'nullable' => (bool) $column['nullable'],
                    'length'   => $length
                ];
            }
        } else {
            foreach (Schema::getColumnListing($table) as $name) {
                $columns[] = [
                    'name'     => $name,
                    'type'     => Schema::getColumnType($table, $name),
                    // Can't tell on Laravel 10's API without Doctrine
                    // DBAL: defaults to nullable (the safer guess for a
                    // generated Validator - a rule that's too strict
                    // would reject legitimate requests).
                    'nullable' => true,
                    'length'   => null
                ];
            }
        }

        return $columns;
    }

    /**
     * SQLite-only fallback for a column's declared length, checked
     * when Schema::getColumns() didn't carry one. Parses the table's
     * raw CREATE TABLE statement (kept verbatim in sqlite_master) for
     * a length next to the column name. Only succeeds if that DDL
     * actually contains one - which a column declared through
     * Schema::string($name, $length) will not, since Laravel's SQLite
     * grammar drops the length entirely (confirmed against real DDL
     * output: 'varchar', never 'varchar(120)'). Only a table created
     * some other way (e.g. a raw SQL migration) could have a length
     * for this to find.
     *
     * @param string $table  The table name
     * @param string $column The column name
     *
     * @return int|null
     */
    private function _sqliteColumnLength(string $table, string $column): ?int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return null;
        }

        $row = DB::selectOne(
            "select sql from sqlite_master where type = 'table' and name = ?",
            [ $table ]
        );

        if (is_null($row)) {
            return null;
        }

        if (preg_match(
            '/["`]?'.preg_quote($column, '/').'["`]?\s+\w+\((\d+)\)/i',
            $row->sql,
            $m
        )) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Detect belongsTo relations from the table's foreign keys.
     *
     * @param string $table The table to introspect
     *
     * @return array<int, array{column: string, method: string, model: string, guessed: bool}>
     */
    private function _introspectRelations(string $table): array
    {
        $relations = [];

        if (method_exists(Schema::getFacadeRoot(), 'getForeignKeys')) {
            // Laravel 11+: real foreign key metadata, so the target
            // table (and therefore the Model to relate to) is known
            // exactly, not guessed from the column name.
            foreach (Schema::getForeignKeys($table) as $foreign_key) {
                if (count($foreign_key['columns']) !== 1) {
                    // Composite foreign keys aren't representable by a
                    // single belongsTo() - left for manual review.
                    continue;
                }

                $column = $foreign_key['columns'][0];

                $relations[] = [
                    'column'  => $column,
                    'method'  => Str::camel(Str::beforeLast($column, '_id')),
                    'model'   => Str::studly(Str::singular($foreign_key['foreign_table'])),
                    'guessed' => false
                ];
            }
        } else {
            // Laravel 10 fallback: no foreign key introspection API
            // available. Guessed purely from the '{x}_id' naming
            // convention - the target Model may well be wrong (e.g.
            // 'author_id' guessed as 'Author' when the real Model is
            // 'User') and needs review, unlike the Laravel 11+ path
            // above which is exact.
            foreach (Schema::getColumnListing($table) as $name) {
                if ($name === 'id' || !Str::endsWith($name, '_id')) {
                    continue;
                }

                $method = Str::camel(Str::beforeLast($name, '_id'));

                $relations[] = [
                    'column'  => $name,
                    'method'  => $method,
                    'model'   => Str::studly($method),
                    'guessed' => true
                ];
            }
        }

        return $relations;
    }

    /**
     * Detect single-column unique indexes, for a unique: validation
     * rule. Only available since Laravel 11's Schema::getIndexes() -
     * silently returns nothing on Laravel 10, same fallback posture as
     * the rest of this command's introspection.
     *
     * @param string $table The table to introspect
     *
     * @return string[] Column names with a unique index
     */
    private function _introspectUniqueColumns(string $table): array
    {
        if (!method_exists(Schema::getFacadeRoot(), 'getIndexes')) {
            return [];
        }

        $unique = [];

        foreach (Schema::getIndexes($table) as $index) {
            if (
                ($index['unique'] ?? false) &&
                !($index['primary'] ?? false) &&
                count($index['columns']) === 1
            ) {
                $unique[] = $index['columns'][0];
            }
        }

        return $unique;
    }

    /**
     * Write the Eloquent Model.
     *
     * @param string $model     The Model class name
     * @param string $table     The DB table name
     * @param array  $columns   The introspected columns
     * @param array  $relations The introspected belongsTo relations
     *
     * @return void
     */
    private function _writeModel(
        string $model, string $table, array $columns, array $relations
    ): void
    {
        $fillable = array_values(array_filter(
            array_column($columns, 'name'),
            fn ($name) => !in_array($name, self::SKIP_FIELDS)
        ));

        $casts = [];

        foreach ($columns as $column) {
            if (in_array($column['name'], self::SKIP_FIELDS)) {
                continue;
            }

            $cast = match (true) {
                in_array($column['type'], ['boolean', 'bool']) => 'boolean',
                in_array($column['type'], ['integer', 'bigint', 'int']) => 'integer',
                in_array($column['type'], ['decimal', 'float', 'double']) => 'float',
                in_array($column['type'], ['date']) => 'date',
                in_array($column['type'], ['datetime', 'timestamp']) => 'datetime',
                in_array($column['type'], ['json', 'array']) => 'array',
                default => null
            };

            if (!is_null($cast)) {
                $casts[$column['name']] = $cast;
            }
        }

        $fillable_php = $this->_phpArray($fillable);
        $casts_php = $this->_phpAssoc($casts, quote_values: true);
        $has_soft_deletes = in_array('deleted_at', array_column($columns, 'name'));
        $uses = $has_soft_deletes
            ? "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n"
            : '';
        $trait = $has_soft_deletes ? "use SoftDeletes;\n\n    " : '';
        $relations_php = $this->_phpRelations($relations);

        $content = <<<PHP
        <?php
        /**
         * {$model} class file
         *
         * PHP Version 8.1
         *
         * @category Model
         * @package  App\\Data\\Models
         * @author   Generated by rivet:make:crud
         * @license  https://opensource.org/licenses/MIT MIT License
         * @link     none
         */
        namespace App\\Data\\Models;

        use Rivet\\Data\\Models\\BaseModel;
        {$uses}
        /**
         * {$model}
         *
         * Generated from the '{$table}' table by rivet:make:crud.
         *
         * @category Model
         * @package  App\\Data\\Models
         * @author   Generated by rivet:make:crud
         * @license  https://opensource.org/licenses/MIT MIT License
         * @link     none
         */
        class {$model} extends BaseModel
        {
            {$trait}/**
             * The uid associated with the model log (see LogTrait).
             * Set to null to disable automatic logging for this model.
             *
             * @var string|null
             */
            public \$log_uid = null;

            /**
             * The attributes that are mass assignable.
             *
             * @var array
             */
            protected \$fillable = {$fillable_php};

            /**
             * The attributes that should be cast.
             *
             * @var array
             */
            protected \$casts = {$casts_php};

            /**
             * -------------------------------------------------------------------------
             * Relations
             * -------------------------------------------------------------------------
             * CRUD::_setRelations() finds these automatically by their
             * return type, no registration needed. Add hasMany()/
             * belongsToMany() methods here by hand - only belongsTo()
             * is detected from foreign keys.
             */
        {$relations_php}}

        PHP;

        $this->_put("app/Data/Models/{$model}.php", $content);
    }

    /**
     * Render the belongsTo() relation methods block for _writeModel().
     *
     * @param array $relations The introspected belongsTo relations
     *
     * @return string
     */
    private function _phpRelations(array $relations): string
    {
        if (empty($relations)) {
            return '';
        }

        $blocks = [];

        foreach ($relations as $relation) {
            $lines = [];
            $lines[] = '';
            $lines[] = '        /**';

            if ($relation['guessed']) {
                $lines[] = '         * GUESSED from the column name only (no foreign '.
                    'key';
                $lines[] = '         * metadata available on this Laravel version) - '.
                    'the';
                $lines[] = '         * target Model below may well be wrong, review it.';
            }

            $lines[] = '         * @return \Illuminate\Database\Eloquent\Relations\BelongsTo';
            $lines[] = '         */';
            $lines[] = "        public function {$relation['method']}(): ".
                '\Illuminate\Database\Eloquent\Relations\BelongsTo';
            $lines[] = '        {';
            $lines[] = "            return \$this->belongsTo({$relation['model']}::class);";
            $lines[] = '        }';

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n", $blocks)."\n";
    }

    /**
     * Write the CRUD Repository.
     *
     * @param string $model     The Model class name
     * @param array  $columns   The introspected columns
     * @param array  $relations The introspected belongsTo relations
     *
     * @return void
     */
    private function _writeRepository(string $model, array $columns, array $relations): void
    {
        $fk_columns = array_column($relations, 'column');
        $filters = [];

        foreach ($columns as $column) {
            if (
                in_array($column['name'], self::SKIP_FIELDS) ||
                in_array($column['name'], $fk_columns)
            ) {
                continue;
            }

            $filters[$column['name']] = $column['name'];
        }

        foreach ($relations as $relation) {
            $filters[$relation['method']] = "relation.{$relation['method']}";
        }

        $filters_php = empty($filters)
            ? "[\n                // 'name' => 'name',\n            ]"
            : $this->_phpAssoc($filters, quote_values: true);

        $content = <<<PHP
        <?php
        /**
         * {$model}Repository class file
         *
         * PHP Version 8.1
         *
         * @category Repository
         * @package  App\\Data\\Repositories
         * @author   Generated by rivet:make:crud
         * @license  https://opensource.org/licenses/MIT MIT License
         * @link     none
         */
        namespace App\\Data\\Repositories;

        use Rivet\\Data\\Repositories\\CRUD;

        /**
         * {$model}Repository
         *
         * \$filters below controls what's exposed to the ?filters[...]
         * and ?sort= query string DSL (see CRUD's class docblock) -
         * pre-filled from the table's columns and detected relations,
         * review and trim what shouldn't actually be publicly
         * filterable/sortable.
         *
         * @category Repository
         * @package  App\\Data\\Repositories
         * @author   Generated by rivet:make:crud
         * @license  https://opensource.org/licenses/MIT MIT License
         * @link     none
         */
        class {$model}Repository extends CRUD
        {
            /**
             * The rows available as filters in the query.
             *
             * @var array
             */
            protected \$filters = {$filters_php};

            /**
             * Register the fields in database and edit the model.
             *
             * @param array \$fields The fields to save
             *
             * @return bool
             */
            protected function register(array \$fields): bool
            {
                return \$this->defaultRegister(\$fields);
            }
        }

        PHP;

        $this->_put("app/Data/Repositories/{$model}Repository.php", $content);
    }

    /**
     * Write the Controller.
     *
     * @param string $model The Model class name
     *
     * @return void
     */
    private function _writeController(string $model): void
    {
        $content = <<<PHP
        <?php
        /**
         * {$model}Controller class file
         *
         * PHP Version 8.1
         *
         * @category Controller
         * @package  App\\Http\\Controllers
         * @author   Generated by rivet:make:crud
         * @license  https://opensource.org/licenses/MIT MIT License
         * @link     none
         */
        namespace App\\Http\\Controllers;

        use Rivet\\Http\\Controllers\\BaseController;

        /**
         * {$model}Controller
         *
         * Empty on purpose: list/show/add/massAdd/edit/massEdit/remove/
         * massRemove are all inherited from BaseController, which
         * resolves {$model}Repository automatically by naming
         * convention. Override a method here only when this entity
         * needs custom behaviour; add its name to \$AUTH_UNLIMITED if it
         * should bypass the per-user query scoping.
         *
         * @category Controller
         * @package  App\\Http\\Controllers
         * @author   Generated by rivet:make:crud
         * @license  https://opensource.org/licenses/MIT MIT License
         * @link     none
         */
        class {$model}Controller extends BaseController
        {
            // static \$AUTH_UNLIMITED = [ 'list', 'show' ];
        }

        PHP;

        $this->_put("app/Http/Controllers/{$model}Controller.php", $content);
    }

    /**
     * Write the Validator.
     *
     * @param string   $model            The Model class name
     * @param string   $table            The DB table name
     * @param array    $columns          The introspected columns
     * @param string[] $unique_columns   Columns with a unique index
     *
     * @return void
     */
    private function _writeValidator(
        string $model, string $table, array $columns, array $unique_columns
    ): void
    {
        $rules = [];

        foreach ($columns as $column) {
            if (in_array($column['name'], self::SKIP_FIELDS)) {
                continue;
            }

            $is_email = $column['name'] === 'email' ||
                Str::endsWith($column['name'], '_email');

            $type_rule = match (true) {
                $is_email => 'email',
                in_array($column['type'], ['boolean', 'bool']) => 'boolean',
                in_array($column['type'], ['integer', 'bigint', 'int']) => 'integer',
                in_array($column['type'], ['decimal', 'float', 'double']) => 'numeric',
                in_array($column['type'], ['date']) => 'date',
                in_array($column['type'], ['datetime', 'timestamp']) => 'date',
                default => 'string'
            };

            $parts = [ $column['nullable'] ? 'nullable' : 'required', $type_rule ];

            // A length only carries a useful max: for string-like
            // columns - a numeric column's "length" (its digit count)
            // isn't a value range and would produce a misleading rule.
            if (!is_null($column['length']) && $type_rule === 'string') {
                $parts[] = "max:{$column['length']}";
            }

            if (in_array($column['name'], $unique_columns)) {
                $parts[] = "unique:{$table},{$column['name']}";
            }

            $rules[$column['name']] = implode('|', $parts);
        }

        $rules_php = $this->_phpAssoc($rules, quote_values: true);

        $content = <<<PHP
        <?php
        /**
         * {$model}Validator class file
         *
         * PHP Version 8.1
         *
         * @category Validator
         * @package  App\\Data\\Validators
         * @author   Generated by rivet:make:crud
         * @license  https://opensource.org/licenses/MIT MIT License
         * @link     none
         */
        namespace App\\Data\\Validators;

        use Rivet\\Services\\ValidatorService;

        /**
         * {$model}Validator
         *
         * Rules were inferred from column types, nullability, length,
         * and unique indexes at generation time - review them by hand:
         * a NOT NULL column with a DB-level default (e.g. a timestamp
         * default) will show up as 'required' here even though the
         * client legitimately doesn't need to send it. Add 'confirmed'/
         * regex:/... where the DB schema alone can't tell.
         *
         * @category Validator
         * @package  App\\Data\\Validators
         * @author   Generated by rivet:make:crud
         * @license  https://opensource.org/licenses/MIT MIT License
         * @link     none
         */
        class {$model}Validator extends ValidatorService
        {
            /**
             * The set of rules of the Validator child.
             *
             * @var array
             */
            protected \$rules = {$rules_php};
        }

        PHP;

        $this->_put("app/Data/Validators/{$model}Validator.php", $content);
    }

    /**
     * Write a migration matching the table as it exists today - useful
     * to bring an existing (pre-Laravel) database schema under version
     * control after the fact, without pretending the table was created
     * through Laravel originally.
     *
     * @param string $model   The Model class name
     * @param string $table   The DB table name
     * @param array  $columns The introspected columns
     *
     * @return void
     */
    private function _writeMigration(string $model, string $table, array $columns): void
    {
        $lines = [];

        foreach ($columns as $column) {
            if (in_array($column['name'], [ 'id', 'created_at', 'updated_at' ])) {
                continue;
            }

            if ($column['name'] === 'deleted_at') {
                $lines[] = "\$table->softDeletes();";
                continue;
            }

            $method = match (true) {
                in_array($column['type'], ['boolean', 'bool']) => 'boolean',
                in_array($column['type'], ['integer', 'int']) => 'integer',
                in_array($column['type'], ['bigint']) => 'unsignedBigInteger',
                in_array($column['type'], ['decimal', 'float', 'double']) => 'decimal',
                in_array($column['type'], ['date']) => 'date',
                in_array($column['type'], ['datetime', 'timestamp']) => 'timestamp',
                in_array($column['type'], ['json', 'array']) => 'json',
                in_array($column['type'], ['text']) => 'text',
                default => 'string'
            };

            $nullable = $column['nullable'] ? "->nullable()" : '';
            $lines[] = "\$table->{$method}('{$column['name']}'){$nullable};";
        }

        $body = implode("\n            ", $lines);
        $class = 'Create'.Str::studly($table).'Table';
        $filename = date('Y_m_d_His')."_create_{$table}_table.php";

        $content = <<<PHP
        <?php
        // Generated by rivet:make:crud --migration, from the '{$table}'
        // table AS IT EXISTS TODAY. This does not replace the migration
        // history of a table created before this file existed - it is
        // meant to bring an already-live schema under version control,
        // for fresh environments (CI, new dev machines) going forward.
        // Review types/defaults/indexes by hand before relying on it.

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table) {
                    \$table->id();
                    {$body}
                    \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };

        PHP;

        $this->_put("database/migrations/{$filename}", $content);
    }

    /**
     * Print the route registration snippet for the generated entity.
     * Deliberately printed to the console rather than appended to
     * routes/api.php: injecting into a file the developer edits by
     * hand risks corrupting it silently.
     *
     * @param string $model The Model class name
     * @param string $table The DB table name
     *
     * @return void
     */
    private function _printRouteSnippet(string $model, string $table): void
    {
        $prefix = Str::kebab(Str::plural($table));

        $this->line('');
        $this->line('Add to your routes/api.php:');
        $this->line('');
        $this->line("Route::prefix('{$prefix}')->controller('{$model}Controller')".
            "->middleware('dataValidation:{$model}')->group(function () {");
        $this->line("    Route::get('/', 'list');");
        $this->line("    Route::get('{uid}', 'show')->where([ 'uid' => '[0-9]+' ]);");
        $this->line("    Route::post('/', 'add');");
        $this->line("    Route::put('{uid}', 'edit');");
        $this->line("    Route::delete('{uid}', 'remove')->where([ 'uid' => '[0-9]+' ]);");
        $this->line('});');
    }

    /**
     * Write a file relative to the application's base path, refusing
     * to overwrite an existing one unless --force was passed.
     *
     * @param string $relative_path The path, relative to base_path()
     * @param string $content       The file content
     *
     * @return void
     */
    private function _put(string $relative_path, string $content): void
    {
        $path = base_path($relative_path);

        if (file_exists($path) && !$this->option('force')) {
            $this->warn("Skipped {$relative_path} (already exists, use --force to overwrite)");

            return;
        }

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->info("Created {$relative_path}");
    }

    /**
     * Render a PHP list array as source code.
     *
     * @param array $values The values
     *
     * @return string
     */
    private function _phpArray(array $values): string
    {
        if (empty($values)) {
            return '[]';
        }

        $items = implode(', ', array_map(fn ($v) => "'{$v}'", $values));

        return "[ {$items} ]";
    }

    /**
     * Render a PHP associative array as source code.
     *
     * @param array $values       The key => value pairs
     * @param bool  $quote_values Whether to quote the values as strings
     *
     * @return string
     */
    private function _phpAssoc(array $values, bool $quote_values = false): string
    {
        if (empty($values)) {
            return '[]';
        }

        $items = [];

        foreach ($values as $key => $value) {
            $value = $quote_values ? "'{$value}'" : $value;
            $items[] = "'{$key}' => {$value}";
        }

        return "[\n                ".implode(",\n                ", $items)."\n            ]";
    }
}
