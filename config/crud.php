<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This user model.
    |
    */
    'user_model' => env('USER_MODEL', \Rivet\Data\Models\Auth\User::class),

    /*
    |--------------------------------------------------------------------------
    | User Relation
    |--------------------------------------------------------------------------
    |
    | This user name in other models relations.
    |
    */
    'user_relation' => env('USER_RELATION', 'user'),

    /*
    |--------------------------------------------------------------------------
    | User Foreign Key
    |--------------------------------------------------------------------------
    |
    | This user foreign key used in relationships to secure the getters.
    |
    */
    'user_fk' => env('USER_FK', 'user_id'),
];
