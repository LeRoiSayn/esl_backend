<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your views. Of course
    | the usual Laravel view path has already been registered for you.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    */

    // Note: realpath() returns false if the directory doesn't exist yet, which can
    // cause "Please provide a valid cache path." in containerized deployments.
    //
    // Also, Render (and some dashboards) can set VIEW_COMPILED_PATH to an empty
    // string. Treat empty as "not set" so we still use the default path.
    'compiled' => (function () {
        $fromEnv = env('VIEW_COMPILED_PATH');
        return !empty($fromEnv) ? $fromEnv : storage_path('framework/views');
    })(),

];
