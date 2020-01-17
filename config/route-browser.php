<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled?
    |--------------------------------------------------------------------------
    |
    | By default, Route Browser is only enabled in the 'local' environment,
    | and only when debugging is enabled.
    |
    */

    'enabled' => env('ROUTE_BROWSER_ENABLED', null),

    /*
    |--------------------------------------------------------------------------
    | Route Path
    |--------------------------------------------------------------------------
    |
    | The URI path where Route Browser will be accessible.
    | The default is /routes, but you can change it to avoid conflicts.
    |
    */

    'path' => 'routes',

    'filters' => [
        '_debugbar*',
        '_ignition*'
    ]

];
