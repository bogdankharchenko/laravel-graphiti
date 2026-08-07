<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Graphiti Service URL
    |--------------------------------------------------------------------------
    |
    | Base URL of the Graphiti REST service (the zepai/graphiti container).
    |
    */

    'url' => env('GRAPHITI_URL', 'http://localhost:8000'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait for a response. Ingestion (POST /messages) only queues
    | work server-side, so responses are fast; search does hybrid retrieval
    | and can take a few seconds on large graphs.
    |
    */

    'timeout' => (int) env('GRAPHITI_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    |
    | Transient failures (connection errors, 5xx) are retried this many
    | times with the given sleep between attempts. Set times to 0 to
    | disable retrying.
    |
    */

    'retry' => [
        'times' => (int) env('GRAPHITI_RETRY_TIMES', 2),
        'sleep_ms' => (int) env('GRAPHITI_RETRY_SLEEP_MS', 200),
    ],

];
