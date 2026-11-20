<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\AssessmentsServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\AwardServiceProvider;
use App\Providers\CertificatesServiceProvider;
use App\Providers\ContactServiceProvider;
use App\Providers\ContentServiceProvider;
use App\Providers\CriteriaServiceProvider;
use App\Providers\DashboardServiceProvider;
use App\Providers\DatabaseSafetyServiceProvider;
use App\Providers\EntitiesServiceProvider;
use App\Providers\FinanceServiceProvider;
use App\Providers\IntegrationServiceProvider;
use App\Providers\NotificationMailServiceProvider;
use App\Providers\NotificationTextsServiceProvider;
use App\Providers\RateLimitServiceProvider;
use App\Providers\SearchServiceProvider;
use App\Providers\WatermarkServiceProvider;

return [
    AppServiceProvider::class,
    AssessmentsServiceProvider::class,
    AuthorizationServiceProvider::class,
    AwardServiceProvider::class,
    CertificatesServiceProvider::class,
    ContactServiceProvider::class,
    ContentServiceProvider::class,
    CriteriaServiceProvider::class,
    DashboardServiceProvider::class,
    DatabaseSafetyServiceProvider::class,
    EntitiesServiceProvider::class,
    FinanceServiceProvider::class,
    IntegrationServiceProvider::class,
    NotificationMailServiceProvider::class,
    NotificationTextsServiceProvider::class,
    RateLimitServiceProvider::class,
    SearchServiceProvider::class,
    WatermarkServiceProvider::class,
];
