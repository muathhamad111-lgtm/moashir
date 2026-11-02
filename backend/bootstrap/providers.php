<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\AssessmentsServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\AwardServiceProvider;
use App\Providers\CriteriaServiceProvider;
use App\Providers\DatabaseSafetyServiceProvider;
use App\Providers\EntitiesServiceProvider;
use App\Providers\FinanceServiceProvider;
use App\Providers\IntegrationServiceProvider;
use App\Providers\RateLimitServiceProvider;
use App\Providers\SearchServiceProvider;

return [
    AppServiceProvider::class,
    AssessmentsServiceProvider::class,
    AuthorizationServiceProvider::class,
    AwardServiceProvider::class,
    CriteriaServiceProvider::class,
    DatabaseSafetyServiceProvider::class,
    EntitiesServiceProvider::class,
    FinanceServiceProvider::class,
    IntegrationServiceProvider::class,
    RateLimitServiceProvider::class,
    SearchServiceProvider::class,
];
