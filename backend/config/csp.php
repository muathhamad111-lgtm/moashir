<?php

declare(strict_types=1);

use App\Support\Security\ApiContentSecurityPolicy;
use Spatie\Csp\Nonce\RandomString;

return [

    'presets' => [
        ApiContentSecurityPolicy::class,
    ],

    'directives' => [

    ],

    'report_only_presets' => [

    ],

    'report_only_directives' => [

    ],

    'report_uri' => env('CSP_REPORT_URI', ''),

    'report_only_uri' => env('CSP_REPORT_ONLY_URI', ''),

    'report_to' => env('CSP_REPORT_TO', ''),

    'report_only_to' => env('CSP_REPORT_ONLY_TO', ''),

    'reporting_endpoints' => [

    ],

    'enabled' => env('CSP_ENABLED', true),

    'enabled_while_hot_reloading' => env('CSP_ENABLED_WHILE_HOT_RELOADING', false),

    'nonce_generator' => RandomString::class,

    'nonce_enabled' => env('CSP_NONCE_ENABLED', false),
];
