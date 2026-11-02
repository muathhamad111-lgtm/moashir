<?php

declare(strict_types=1);

use App\Http\Controllers\WellKnown\ApplePayDomainAssociationController;
use App\Support\Integrations\IntegrationUrls;
use Illuminate\Support\Facades\Route;

Route::get(IntegrationUrls::APPLE_PAY_ASSOCIATION_PATH, ApplePayDomainAssociationController::class)
    ->middleware('throttle:public')
    ->name('well-known.apple-pay-domain-association');
