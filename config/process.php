<?php

declare(strict_types=1);

/**
 * Process configuration.
 *
 * This configuration is used to configure the process
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
return [
    'timeout' => env('PROCESS_TIMEOUT', 3600),
];
