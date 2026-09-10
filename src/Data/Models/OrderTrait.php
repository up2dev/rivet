<?php
/**
 * OrderTrait class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * OrderTrait
 *
 * @category Model
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
trait OrderTrait
{
    /**
     * Boot the trait
     *
     * @return void
     */
    protected static function bootOrderTrait(): void
    {
        static::creating(function (Model $model) {
            if (
                $model->is_ordered &&
                Schema::hasColumn($model->getTable(), 'order') &&
                is_null($model->order)
            ) {
                $class = get_class($model);
                $query = $class::query();

                if (
                    property_exists($model, 'order_grouped_by') &&
                    !is_null($model->order_grouped_by)
                ) {
                    $group = $model->order_grouped_by;
                    $query = $query->where($group, $model->$group);
                }

                if (Schema::hasColumn($model->getTable(), 'deleted_at')) {
                    $query = $query->where('deleted_at', null);
                }

                $model->order = $query->count() + 1;
            }
        });

        static::created(function (Model $model) {
            if (
                $model->is_ordered &&
                Schema::hasColumn($model->getTable(), 'order')
            ) {
                $class = get_class($model);
                $query = $class::where(
                    'order', '>=', $model->order
                )->where('id', '<>', $model->id);

                if (
                    property_exists($model, 'order_grouped_by') &&
                    !is_null($model->order_grouped_by)
                ) {
                    $group = $model->order_grouped_by;
                    $query = $query->where($group, $model->$group);
                }

                if (Schema::hasColumn($model->getTable(), 'deleted_at')) {
                    $query = $query->where('deleted_at', null);
                }

                $others = $query->get();

                foreach ($others as $other) {
                    $other->order += 1;
                    $other->saveQuietly();
                }
            }
        });

        static::updating(function (Model $model) {
            if (
                $model->is_ordered &&
                Schema::hasColumn($model->getTable(), 'order')
            ) {
                $old_order = $model->getOriginal('order');

                // if (!is_null($model->deleted_at)) {
                //     $model->order = 0;
                // }

                if (!is_null($model->order) && $model->order !== $old_order) {
                    $is_asc = $model->order > $old_order;
                    $class = get_class($model);
                    $query = $class::where(
                        'order', ($is_asc? '>': '<'), $old_order
                    )->where(
                        'order', ($is_asc? '<=': '>='), $model->order
                    )->where('id', '<>', $model->id);

                    if (
                        property_exists($model, 'order_grouped_by') &&
                        !is_null($model->order_grouped_by)
                    ) {
                        $group = $model->order_grouped_by;
                        $query = $query->where($group, $model->$group);
                    }
    
                    if (Schema::hasColumn($model->getTable(), 'deleted_at')) {
                        $query = $query->where('deleted_at', null);
                    }
    
                    $others = $query->get();

                    foreach ($others as $other) {
                        $other->order += ($is_asc? -1: 1);
                        $other->saveQuietly();
                    }
                } else {
                    $model->order = $old_order;
                }
            }
        });

        static::deleted(function (Model $model) {
            if (
                $model->is_ordered &&
                Schema::hasColumn($model->getTable(), 'order')
            ) {
                $class = get_class($model);
                $query = $class::where('order', '>=', $model->order);

                if (
                    property_exists($model, 'order_grouped_by') &&
                    !is_null($model->order_grouped_by)
                ) {
                    $group = $model->order_grouped_by;
                    $query = $query->where($group, $model->$group);
                }

                if (Schema::hasColumn($model->getTable(), 'deleted_at')) {
                    $query = $query->where('deleted_at', null);

                    $model->order = 0;
                    $model->saveQuietly();
                }

                $others = $query->get();

                foreach ($others as $other) {
                    $other->order--;
                    $other->saveQuietly();
                }
            }
        });
    }
}
