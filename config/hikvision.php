<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hikvision Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for interacting with the local C++ ISUP 5.0 gateway daemon
    | and public device connection parameters.
    |
    */

    'gateway_url' => env('HIKVISION_GATEWAY_URL', 'http://127.0.0.1:7661'),

    'das_address' => env('HIKVISION_DAS_ADDRESS', '193.180.213.188'),

    'cms_port' => (int) env('HIKVISION_CMS_PORT', 7660),

    'alarm_port' => (int) env('HIKVISION_ALARM_PORT', 7200),

    'timeout' => (int) env('HIKVISION_TIMEOUT', 10),

    'gateway_token' => env('HIKVISION_GATEWAY_TOKEN', ''),

    'gateway_token_required' => (bool) env('HIKVISION_GATEWAY_TOKEN_REQUIRED', false),

    'use_device_time' => (bool) env('HIKVISION_USE_DEVICE_TIME', false),

    'restart_command' => env('HIKVISION_RESTART_COMMAND', 'sudo systemctl restart hikvision-isup'),
];
