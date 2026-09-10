<?php
/**
 * InheritanceTrait class file
 *
 * PHP Version 8.1
 *
 * @category Trait
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * InheritanceTrait
 *
 * @category Trait
 * @package  Rivet\Data\Models
 * @license  https://opensource.org/licenses/MIT MIT License
 */
trait InheritanceTrait
{
    // /**
    //  * Initialize the trait
    //  *
    //  * @return void
    //  */
    // protected function initializeInheritanceModel()
    // {
    //     dd('test');
    // }

    /**
     * Boot the trait
     *
     * @return void
     */
    protected static function bootInheritanceTrait(): void
    {
        // 'ignored' => fields not affected by cascading
        // 'associations' => fields with different names ([ 'parent' => 'child', ... ])
        // FOR THE CHILD
        // 'locked' => fields can't be edited
        // or
        // 'unlocked' => other fields can't be edited
        // FOR THE PARENT
        // 'cascaded' => fields are cascaded
        // or
        // 'uncascaded' => other fields are cascaded
        // FOR BOTH
        // 'inherited' => fields are locked and cascaded
        // or
        // 'uninherited' => other fields are locked and cascaded
        static::saved(function(Model $model) {
            $settings = $model->inheritanceModel();
            $pkey = array_key_exists(
                'parent_relation', $settings
            )? $settings['parent_relation']: 'parent';
            $ckey = array_key_exists(
                'children_relation', $settings
            )? $settings['children_relation']: 'children';
            $parent = $model->$pkey;
            $children = $model->$ckey;

            if (!array_key_exists('ignored', $settings)) {
                $settings['ignored'] = [];
            }

            $settings['ignored'] = array_merge(
                $settings['ignored'], [
                    'id', "{$model->getTable()}_id",
                    'created_at', 'updated_at', 'deleted_at',
                    $pkey, $ckey
                ]
            );

            if (!is_null($parent)) {
                $psettings = $parent->inheritanceModel();

                foreach ($psettings as $attr => $values) {
                    if (key_exists($attr, $settings) && is_array($values)) {
                        $psettings[$attr] = array_unique(
                            array_merge($values, $settings)[$attr]
                        );
                    }
                }

                $psettings = array_merge($psettings, $settings);

                foreach ($model->attributes as $attribute => $value) {
                    if (
                        !in_array($attribute, $psettings['ignored']) && (
                            array_key_exists($attribute, $parent->attributes) ||
                            (
                                array_key_exists('associations', $psettings) &&
                                array_key_exists($attribute, $psettings['associations']) &&
                                array_key_exists($psettings['associations'][$attribute], $parent->attributes)
                            )
                        ) && // the attribute exists in parent model
                        $value !== $parent->$attribute && ( // the child attribute value is different from the parent
                            ( // the attribute is locked or not unlocked
                                array_key_exists('locked', $psettings) &&
                                in_array($attribute, $psettings['locked'])
                            ) || (
                                array_key_exists('unlocked', $psettings) &&
                                !in_array($attribute, $psettings['unlocked'])
                            ) || ( // the attribute is inherited or not uninherited
                                array_key_exists('inherited', $psettings) &&
                                in_array($attribute, $psettings['inherited'])
                            ) || (
                                array_key_exists('uninherited', $psettings) &&
                                !in_array($attribute, $psettings['uninherited'])
                            )
                        )
                    ) {
                        if (
                            array_key_exists('associations', $psettings) &&
                            array_key_exists($attribute, $psettings['associations']) &&
                            array_key_exists($psettings['associations'][$attribute], $parent->attributes)
                        ) {
                            $model->{$psettings['associations'][$attribute]} = $parent->$attribute;
                        } else {
                            $model->$attribute = $parent->$attribute;
                        }
                    }
                }

                $model->saveQuietly();
            }

            foreach ($children as $child) {
                $csettings = $child->inheritanceModel();

                foreach ($csettings as $attr => $values) {
                    if (key_exists($attr, $settings) && is_array($values)) {
                        $csettings[$attr] = array_unique(
                            array_merge($values, $settings)[$attr]
                        );
                    }
                }

                $csettings = array_merge($csettings, $settings);

                foreach ($model->attributes as $attribute => $value) {
                    if (
                        !in_array($attribute, $csettings['ignored']) && (
                            array_key_exists($attribute, $child->attributes) ||
                            (
                                array_key_exists('associations', $csettings) &&
                                array_key_exists($attribute, $csettings['associations']) &&
                                array_key_exists($csettings['associations'][$attribute], $child->attributes)
                            )
                        ) && // the attribute exists in child model
                        $value !== $child->$attribute && // the parent attribute value is different from the child
                        $model->getOriginal($attribute) === $child->$attribute && ( // the child attribute wasn't overwriten
                            ( // the attribute is cascaded or not not cascaded
                                array_key_exists('cascaded', $csettings) &&
                                in_array($attribute, $csettings['cascaded'])
                            ) || (
                                array_key_exists('uncascaded', $csettings) &&
                                !in_array($attribute, $csettings['uncascaded'])
                            ) || ( // the attribute is inherited or not uninherited
                                array_key_exists('inherited', $csettings) &&
                                in_array($attribute, $csettings['inherited'])
                            ) || (
                                array_key_exists('uninherited', $csettings) &&
                                !in_array($attribute, $csettings['uninherited'])
                            )
                        )
                    ) {
                        // dump($attribute);
                        if (
                            array_key_exists('associations', $csettings) &&
                            array_key_exists($attribute, $csettings['associations']) &&
                            array_key_exists($csettings['associations'][$attribute], $child->attributes)
                        ) {
                            $child->{$psettings['associations'][$attribute]} = $model->$attribute;
                        } else {
                            $child->$attribute = $model->$attribute;
                        }
                    }
                }

                $child->save(); // trigger the cascade for children of children if required
            }

            return $model;
        });
    }

    /**
     * -------------------------------------------------------------------------
     * Mutators
     * -------------------------------------------------------------------------
     */

    public function syncInherit(): void
    {
        $settings = $this->inheritanceModel();
        $pkey = array_key_exists(
            'parent_relation', $settings
        )? $settings['parent_relation']: 'parent';
        $ckey = array_key_exists(
            'children_relation', $settings
        )? $settings['children_relation']: 'children';
        $parent = $this->$pkey;
        $children = $this->$ckey;
        $hmrelations = [];
        $mmrelations = [];

        if (!array_key_exists('ignored', $settings)) {
            $settings['ignored'] = [];
        }

        $settings['ignored'] = array_merge(
            $settings['ignored'], [
                'id', "{$this->getTable()}_id",
                'created_at', 'updated_at', 'deleted_at',
                $pkey, $ckey
            ]
        );

        $reflect = new \ReflectionClass($this);
        foreach ($reflect->getMethods() as $method) {
            if (!is_null($method->getReturnType())) {
                $type = explode('\\', $method->getReturnType()->getName());
                $type = $type[count($type) - 1];
                $method_name = $method->getName();

                switch (Str::lower($type)) {
                    case 'hasmany':
                        $hmrelations[$method_name] = (
                            $reflect->newInstance()
                        )->{$method->getName()}();
                        break;

                    case 'belongstomany':
                        $mmrelations[$method_name] = (
                            $reflect->newInstance()
                        )->{$method->getName()}();
                        break;
                }
            }
        }

        if (!is_null($parent)) {
            $psettings = $parent->inheritanceModel();

            foreach ($psettings as $attr => $values) {
                if (key_exists($attr, $settings) && is_array($values)) {
                    $psettings[$attr] = array_unique(
                        array_merge($values, $settings)[$attr]
                    );
                }
            }

            $psettings = array_merge($psettings, $settings);

            foreach ($mmrelations as $mname => $mreturn) {
                if (
                    !in_array($mname, $psettings['ignored']) &&
                    method_exists($parent, $mname) && ( // the method exists in parent model
                        ( // the attribute is locked or not unlocked
                            array_key_exists('locked', $psettings) &&
                            in_array($mname, $psettings['locked'])
                        ) || (
                            array_key_exists('unlocked', $psettings) &&
                            !in_array($mname, $psettings['unlocked'])
                        ) || ( // the attribute is inherited or not uninherited
                            array_key_exists('inherited', $psettings) &&
                            in_array($mname, $psettings['inherited'])
                        ) || (
                            array_key_exists('uninherited', $psettings) &&
                            !in_array($mname, $psettings['uninherited'])
                        )
                    ) &&
                    $mreturn->getRelatedPivotKeyName() === $parent->$mname()->getRelatedPivotKeyName() // The relations target a common table
                ) {
                    $parent->load($mname);
                    $sync = [];

                    foreach ($parent->$mname as $value) {
                        // $id = $value->{$mreturn->getRelatedPivotKeyName()};
                        $id = $value->{$mreturn->getRelatedKeyName()};

                        if (empty($mreturn->getPivotColumns())) {
                            array_push($sync, $id);
                        } else {
                            $sync[$id] = [];

                            foreach ($mreturn->getPivotColumns() as $attr) {
                                $sync[$id][$attr] = $value->$attr;
                            }
                        }
                    }

                    $this->$mname()->sync($sync);
                }
            }

            // foreach ($hmrelations as $mname => $mreturn) {
            //     if (
            //         !in_array($mname, $psettings['ignored']) &&
            //         method_exists($parent, $mname) && ( // the method exists in parent model
            //             ( // the attribute is locked or not unlocked
            //                 array_key_exists('locked', $psettings) &&
            //                 in_array($mname, $psettings['locked'])
            //             ) || (
            //                 array_key_exists('unlocked', $psettings) &&
            //                 !in_array($mname, $psettings['unlocked'])
            //             ) || ( // the attribute is inherited or not uninherited
            //                 array_key_exists('inherited', $psettings) &&
            //                 in_array($mname, $psettings['inherited'])
            //             ) || (
            //                 array_key_exists('uninherited', $psettings) &&
            //                 !in_array($mname, $psettings['uninherited'])
            //             )
            //         )
            //     ) {
            //     }
            // }
        }

        foreach ($children as $child) {
            $csettings = $child->inheritanceModel();

            foreach ($csettings as $attr => $values) {
                if (key_exists($attr, $settings) && is_array($values)) {
                    $csettings[$attr] = array_unique(
                        array_merge($values, $settings)[$attr]
                    );
                }
            }

            $csettings = array_merge($csettings, $settings);

            foreach ($mmrelations as $mname => $mreturn) {
                if (
                    !in_array($mname, $csettings['ignored']) &&
                    method_exists($child, $mname) && ( // the method exists in child model
                        ( // the attribute is cascaded or not cascaded
                            array_key_exists('cascaded', $csettings) &&
                            in_array($mname, $csettings['cascaded'])
                        ) || (
                            array_key_exists('uncascaded', $csettings) &&
                            !in_array($mname, $csettings['uncascaded'])
                        ) || ( // the attribute is inherited or not uninherited
                            array_key_exists('inherited', $csettings) &&
                            in_array($mname, $csettings['inherited'])
                        ) || (
                            array_key_exists('uninherited', $csettings) &&
                            !in_array($mname, $csettings['uninherited'])
                        )
                    ) &&
                    $mreturn->getRelatedPivotKeyName() === $child->$mname()->getRelatedPivotKeyName() // The relations target a common table
                ) {
                    $this->load($mname);
                    $sync = [];

                    foreach ($this->$mname as $value) {
                        // $id = $value->{$mreturn->getRelatedPivotKeyName()};
                        $id = $value->{$mreturn->getRelatedKeyName()};

                        if (empty($mreturn->getPivotColumns())) {
                            array_push($sync, $id);
                        } else {
                            $sync[$id] = [];

                            foreach ($mreturn->getPivotColumns() as $attr) {
                                $sync[$id][$attr] = $value->$attr;
                            }
                        }
                    }

                    // dump($sync);
                    // dump($child->id);
                    // dump($child->$mname->toArray());
                    $child->$mname()->sync($sync);
                    $child->syncInherit();
                }
            }

            // foreach ($hmrelations as $mname => $mreturn) {
            //     if (
            //         !in_array($mname, $psettings['ignored']) &&
            //         method_exists($child, $mname) && ( // the method exists in parent model
            //             ( // the attribute is cascaded or not uncascaded
            //                 array_key_exists('cascaded', $psettings) &&
            //                 in_array($mname, $psettings['cascaded'])
            //             ) || (
            //                 array_key_exists('uncascaded', $psettings) &&
            //                 !in_array($mname, $psettings['uncascaded'])
            //             ) || ( // the attribute is inherited or not uninherited
            //                 array_key_exists('inherited', $psettings) &&
            //                 in_array($mname, $psettings['inherited'])
            //             ) || (
            //                 array_key_exists('uninherited', $psettings) &&
            //                 !in_array($mname, $psettings['uninherited'])
            //             )
            //         )
            //     ) {
            //     }
            // }
        }
    }
}
