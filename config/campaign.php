<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The election
    |--------------------------------------------------------------------------
    */

    'election_name' => env('CAMPAIGN_ELECTION_NAME', 'Ebonyi State Governorship Election'),

    'election_date' => env('CAMPAIGN_ELECTION_DATE', '2027-02-06'),

    // Times are stored in UTC and shown in this timezone.
    'timezone' => env('CAMPAIGN_TIMEZONE', 'Africa/Lagos'),

    // The 13 LGAs of Ebonyi State. The register import checks against them.
    'lgas' => [
        'Abakaliki', 'Afikpo North', 'Afikpo South', 'Ebonyi', 'Ezza North', 'Ezza South', 'Ikwo',
        'Ishielu', 'Ivo', 'Izzi', 'Ohaozara', 'Ohaukwu', 'Onicha',
    ],

    /*
    |--------------------------------------------------------------------------
    | Set-up
    |--------------------------------------------------------------------------
    |
    | The first admin account is created in the browser with ADMIN_PASSWORD
    | as a one-time setup key (the host has no terminal).
    |
    */

    'admin_password' => env('ADMIN_PASSWORD'),

    // Background work runs after web requests and from the pinger URL / host
    // cron; see App\Support\BackgroundRunner.
    'background_runner' => (bool) env('BACKGROUND_RUNNER', true),

    // Field agents stay signed in on their phone this long, so they can
    // work offline for days.
    'remember_days' => (int) env('CAMPAIGN_REMEMBER_DAYS', 30),

    // Heavy aggregates (zones, segments, leaderboards) are cached this long.
    'aggregate_cache_seconds' => (int) env('AGGREGATE_CACHE_SECONDS', 300),

];
