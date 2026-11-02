<?php

declare(strict_types=1);

use App\Models\Audit\Audit;
use App\Support\Audit\ActorEntityResolver;
use App\Support\Audit\ActorRolesResolver;
use App\Support\Audit\ReasonResolver;
use App\Support\Audit\RequestIdResolver;
use App\Support\Audit\UrlWithoutQueryResolver;
use OwenIt\Auditing\Resolvers\IpAddressResolver;
use OwenIt\Auditing\Resolvers\UserAgentResolver;
use OwenIt\Auditing\Resolvers\UserResolver;

return [

    'enabled' => env('AUDITING_ENABLED', true),

    'implementation' => Audit::class,

    'user' => [
        'morph_prefix' => 'user',
        'guards' => [
            'web',
        ],
        'resolver' => UserResolver::class,
    ],

    'resolvers' => [
        'ip_address' => IpAddressResolver::class,
        'user_agent' => UserAgentResolver::class,
        'url' => UrlWithoutQueryResolver::class,
        'reason' => ReasonResolver::class,
        'request_id' => RequestIdResolver::class,
        'actor_roles' => ActorRolesResolver::class,
        'actor_entity_id' => ActorEntityResolver::class,
    ],

    'events' => [
        'created',
        'updated',
        'deleted',
        'restored',
    ],

    'strict' => false,

    'exclude' => ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'],

    'empty_values' => true,
    'allowed_empty_values' => [
        'retrieved',
    ],

    'allowed_array_values' => false,

    'timestamps' => false,

    'threshold' => 0,

    'driver' => 'database',

    'drivers' => [
        'database' => [
            'table' => 'audits',
            'connection' => null,
        ],
    ],

    'queue' => [
        'enable' => false,
        'connection' => 'sync',
        'queue' => 'default',
        'delay' => 0,
    ],

    'console' => true,
];
