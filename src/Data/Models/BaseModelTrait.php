<?php
/**
 * BaseModelTrait class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * BaseModelTrait
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
trait BaseModelTrait
{
    /**
     * Boot the trait
     *
     * @return void
     */
    protected static function bootBaseModelTrait(): void
    {
        static::deleted(function (Model $model) {
            if (Schema::hasColumn($model->getTable(), 'deleted_at')) {
                $attrs = $model->getAttributes();

                if (isset($attrs['uid'])) {
                    $model->uid = Str::replace('=', '', base64_encode($model->uid));
                    $model->uid = Str::replace('+', '', $model->uid);
                    $model->uid = Str::replace('/', '', $model->uid);
                }

                if (isset($attrs['login'])) {
                    $model->login = Str::replace('=', '', base64_encode($model->login));
                    $model->login = Str::replace('+', '', $model->login);
                    $model->login = Str::replace('/', '', $model->login);
                }

                if (isset($attrs['email'])) {
                    $model->email = Hash::make($model->email);
                }

                if (isset($attrs['password'])) {
                    $model->password = null;
                }

                $model->saveQuietly();
            }
        });
    }
}
