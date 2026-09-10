<?php
/**
 * DBVersionTrait class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DBVersionTrait
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
trait DBVersionTrait
{
    /**
     * Boot the trait. Executes the version's SQL script on creation,
     * logs the outcome, and cancels the save (via Eloquent's 'false'
     * return from a 'creating' listener) if the script fails - so a
     * DBVersion row is only ever persisted once its script has
     * actually run.
     *
     * Hooked on 'creating' rather than 'saving': editing an existing
     * row's 'comments' afterwards must not re-run its script.
     *
     * @return void
     */
    protected static function bootDBVersionTrait(): void
    {
        static::creating(function (DBVersion $model) {
            try {
                DB::unprepared($model->sqlscript);
            } catch (\Throwable $exception) {
                Log::error(
                    "DBVersion [{$model->version}]: SQL script failed, ".
                    'version not registered',
                    [ 'error' => $exception->getMessage() ]
                );

                return false;
            }

            Log::info(
                "DBVersion [{$model->version}]: SQL script executed successfully"
            );

            return true;
        });
    }
}
