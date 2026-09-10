<?php
/**
 * LogTrait class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models;

use Illuminate\Database\Eloquent\Model;
use Rivet\Data\Models\Auth\User;
use Rivet\Data\Models\Log\Log;

/**
 * LogTrait
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
trait LogTrait
{
    /**
     * Boot the trait
     *
     * @return void
     */
    protected static function bootLogTrait(): void
    {
        // retrieved, creating, created, updating, updated, saving, saved, deleting, deleted, trashed, forceDeleted, restoring, restored, and replicating.
        static::created(function (Model $model) {
            if (config('logs.is_logged') && !is_null($model->log_uid)) {
                $log = new Log();

                $log->code = 'DB-CREATED';
                $log->data = $model;
                $log->save();
            }
        });

        static::updated(function (Model $model) {
            if (config('logs.is_logged') && !is_null($model->log_uid)) {
                $log = new Log();

                $log->code = 'DB-UPDATED';
                $log->data = $model;
                $log->save();
            }
        });

        static::deleted(function (Model $model) {
            if (config('logs.is_logged') && !is_null($model->log_uid)) {
                $log = new Log();

                $log->code = 'DB-DELETED';
                $log->data = $model;
                $log->save();

                if ($model instanceof User) {
                    // dump(Log::where(
                    //     'data.body.data.id', $model->id
                    // )->get()->toArray());
                    $logs = Log::where(
                        'data.uid', 'LIKE', '%User%'
                    )->where('data.attributes.id', $model->id)->get();

                    foreach ($logs as $log) {
                        $data = $log->data;

                        if (array_key_exists('login', $data['attributes'])) {
                            $data['attributes']['login'] = $model->login;
                        }

                        if (array_key_exists('email', $data['attributes'])) {
                            $data['attributes']['email'] = $model->email;
                        }

                        if (array_key_exists('original', $data)) {
                            if (array_key_exists('login', $data['original'])) {
                                $data['original']['login'] = $model->login;
                            }

                            if (array_key_exists('email', $data['original'])) {
                                $data['original']['email'] = $model->email;
                            }
                        }

                        $log->data = $data;
                        $log->save();
                    }
                }
            }
        });
    }
}
