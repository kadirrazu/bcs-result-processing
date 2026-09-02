<?php

return [
    'queue' => env('ALLOCATION_QUEUE', 'imports'),

    /*
    |--------------------------------------------------------------------------
    | Allocation Settings (file-configurable)
    |--------------------------------------------------------------------------
    |
    | These values are intentionally configured in source control rather than
    | through an operator UI. After changing this file, clear/rebuild Laravel's
    | config cache, review the values on the Allocation landing page, and freeze
    | the current configuration before starting/re-running Allocation.
    |
    */
    'quota_priority' => ['CFF', 'EM', 'PHC'],

    'provisional_breakup_percentages' => [
        'mq' => 93,
        'cff' => 5,
        'em' => 1,
        'phc' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Locked business rule
    |--------------------------------------------------------------------------
    |
    | Quota breakup applies only when sanctioned total posts are 10 or more.
    | For 1-9 posts: MQ = total_post and CFF/EM/PHC = 0.
    |
    | This value is exposed here for a single transparent source of truth, but
    | Allocation validation intentionally rejects any value other than 10.
    |
    */
    'quota_breakup_minimum_total_posts' => 10,
];
