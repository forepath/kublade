<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_') . '_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,
        'failed'        => 10080,
        'monitored'     => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'connection'   => 'redis',
            'queue'        => ['dispatchers'],
            'balance'      => 'auto',
            'maxProcesses' => 1,
            'maxTime'      => 0,
            'maxJobs'      => 0,
            'memory'       => 128,
            'tries'        => 1,
            'timeout'      => 10800,
            'nice'         => 0,
        ],
        'supervisor-2' => [
            'connection'   => 'redis',
            'queue'        => ['singletons'],
            'balance'      => 'auto',
            'maxProcesses' => 2,
            'maxTime'      => 0,
            'maxJobs'      => 0,
            'memory'       => 128,
            'tries'        => 1,
            'timeout'      => 3600,
            'nice'         => 0,
        ],
        'supervisor-3' => [
            'connection'   => 'redis',
            'queue'        => ['flux_deployment'],
            'balance'      => 'auto',
            'maxProcesses' => 4,
            'maxTime'      => 0,
            'maxJobs'      => 0,
            'memory'       => 256,
            'tries'        => 1,
            'timeout'      => 900,
            'nice'         => 0,
        ],
        'supervisor-4' => [
            'connection' => 'redis',
            'queue'      => [
                'cluster_limit_monitoring',
                'cluster_status_monitoring',
                'flux_status_monitoring',
            ],
            'balance'      => 'auto',
            'maxProcesses' => 4,
            'maxTime'      => 0,
            'maxJobs'      => 0,
            'memory'       => 256,
            'tries'        => 1,
            'timeout'      => 3600,
            'nice'         => 0,
        ],
        'supervisor-5' => [
            'connection'   => 'redis',
            'queue'        => ['template_git_import'],
            'balance'      => 'auto',
            'maxProcesses' => 4,
            'maxTime'      => 0,
            'maxJobs'      => 0,
            'memory'       => 256,
            'tries'        => 1,
            'timeout'      => 1800,
            'nice'         => 0,
        ],
        'supervisor-6' => [
            'connection'   => 'redis',
            'queue'        => ['cluster'],
            'balance'      => 'auto',
            'maxProcesses' => 4,
            'maxTime'      => 0,
            'maxJobs'      => 0,
            'memory'       => 256,
            'tries'        => 1,
            'timeout'      => 900,
            'nice'         => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses'    => 5,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-2' => [
                'maxProcesses'    => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-3' => [
                'maxProcesses'    => 20,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-4' => [
                'maxProcesses'    => 20,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-5' => [
                'maxProcesses'    => 20,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-6' => [
                'maxProcesses'    => 20,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [],
    ],
];
