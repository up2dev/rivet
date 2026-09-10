<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Query distinct
    |--------------------------------------------------------------------------
    |
    | This implement the default behavor for queries. Can always be overwriten
    | by UL parameter distinct.
    |
    */
    'distinct' => false,

    /*
    |--------------------------------------------------------------------------
    | Query order by / conditions / relations
    |--------------------------------------------------------------------------
    |
    | These three are runtime-only in practice - QueryStringToConfig
    | (re)writes them on every request from the ?sort=/?filters=/?with=
    | query params (or a QUERY-method request body), and CRUD reads
    | them back while building the query. Declared here as empty
    | arrays so count() always receives an array rather than null
    | (which would throw) when the corresponding parameter is present
    | but empty, e.g. '?sort='.
    |
    */
    'order_by'   => [],
    'conditions' => [],
    'relations'  => []
];
