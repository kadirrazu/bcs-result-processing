<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Runtime examination connection
    |--------------------------------------------------------------------------
    |
    | The central connection remains Laravel's default connection. The named
    | connection below is rebuilt at runtime for the examination selected in
    | the authenticated user's session.
    |
    */
    'connection' => 'exam',
    'base_connection' => env('EXAM_DB_BASE_CONNECTION', env('DB_CONNECTION', 'mysql')),
    'database_name_pattern' => '/\A[a-zA-Z0-9_]+\z/',
    'sqlite_directory' => database_path('examinations'),
];
