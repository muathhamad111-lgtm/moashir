<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Enums\Settings\PathSetting;
use App\Http\Controllers\Api\V1\Finance\ActivateBankAccountController;
use App\Http\Controllers\Api\V1\Finance\BankAccountController;
use App\Http\Controllers\Api\V1\Finance\DeactivateBankAccountController;
use App\Http\Controllers\Api\V1\Finance\ReorderBankAccountsController;
use App\Http\Controllers\Api\V1\Settings\AssessmentSettingsController;
use App\Http\Controllers\Api\V1\Settings\AuthSettingsController;
use App\Http\Controllers\Api\V1\Settings\BrandingController;
use App\Http\Controllers\Api\V1\Settings\CertificateSettingsController;
use App\Http\Controllers\Api\V1\Settings\FinanceSettingsController;
use App\Http\Controllers\Api\V1\Settings\InsightsSettingsController;
use App\Http\Controllers\Api\V1\Settings\PathGuideController;
use App\Http\Controllers\Api\V1\Settings\PathSettingsController;
use App\Http\Controllers\Api\V1\Settings\PaymentChannelsController;
use App\Models\Finance\BankAccount;
use App\Models\Settings\BrandingTheme;
use Illuminate\Support\Facades\Route;

$finance = 'can:'.Ability::ManageFinanceSettings->value;
$assessment = 'can:'.Ability::ManageAssessmentSettings->value;
$certificates = 'can:'.Ability::ManageCertificateSettings->value;
$auth = 'can:'.Ability::ManageAuthSettings->value;
$viewBranding = 'can:view,'.BrandingTheme::class;
$manageBranding = 'can:manage,'.BrandingTheme::class;
$paymentChannels = 'can:'.Ability::ManagePaymentIntegration->value;
$bankAccounts = 'can:manage,'.BankAccount::class;

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])->prefix('settings')->name('settings.')->group(function () use ($finance, $assessment, $certificates, $auth, $viewBranding, $manageBranding, $paymentChannels, $bankAccounts): void {
    Route::get('finance', [FinanceSettingsController::class, 'show'])->middleware($finance)->name('finance.show');
    Route::put('finance', [FinanceSettingsController::class, 'update'])->middleware([$finance, 'throttle:sensitive'])->name('finance.update');
    Route::get('assessment', [AssessmentSettingsController::class, 'show'])->middleware($assessment)->name('assessment.show');
    Route::put('assessment', [AssessmentSettingsController::class, 'update'])->middleware([$assessment, 'throttle:sensitive'])->name('assessment.update');
    Route::whereIn('path', PathSetting::pathValues())->group(function () use ($assessment): void {
        Route::get('assessment/paths/{path}', [PathSettingsController::class, 'show'])->middleware($assessment)->name('assessment.paths.show');
        Route::put('assessment/paths/{path}', [PathSettingsController::class, 'update'])->middleware([$assessment, 'throttle:sensitive'])->name('assessment.paths.update');
    });
    Route::get('insights', [InsightsSettingsController::class, 'show'])->middleware($assessment)->name('insights.show');
    Route::put('insights', [InsightsSettingsController::class, 'update'])->middleware([$assessment, 'throttle:sensitive'])->name('insights.update');
    Route::get('certificates', [CertificateSettingsController::class, 'show'])->middleware($certificates)->name('certificates.show');
    Route::put('certificates', [CertificateSettingsController::class, 'update'])->middleware([$certificates, 'throttle:sensitive'])->name('certificates.update');
    Route::get('auth', [AuthSettingsController::class, 'show'])->middleware($auth)->name('auth.show');
    Route::put('auth', [AuthSettingsController::class, 'update'])->middleware([$auth, 'throttle:sensitive'])->name('auth.update');
    Route::get('branding', [BrandingController::class, 'show'])->middleware($viewBranding)->name('branding.show');
    Route::put('branding', [BrandingController::class, 'update'])->middleware([$manageBranding, 'throttle:sensitive'])->name('branding.update');
    Route::post('branding/reset', [BrandingController::class, 'reset'])->middleware([$manageBranding, 'throttle:sensitive'])->name('branding.reset');
    Route::post('branding/logo', [BrandingController::class, 'logo'])->middleware([$manageBranding, 'throttle:uploads'])->name('branding.logo');

    Route::prefix('payment')->name('payment.')->group(function () use ($paymentChannels, $bankAccounts): void {
        Route::get('channels', [PaymentChannelsController::class, 'show'])->middleware($paymentChannels)->name('channels.show');
        Route::put('channels', [PaymentChannelsController::class, 'update'])->middleware([$paymentChannels, 'throttle:sensitive'])->name('channels.update');

        Route::prefix('bank-accounts')->name('bank-accounts.')->group(function () use ($bankAccounts): void {
            Route::get('/', [BankAccountController::class, 'index'])->middleware($bankAccounts)->name('index');
            Route::post('/', [BankAccountController::class, 'store'])->middleware([$bankAccounts, 'throttle:sensitive'])->name('store');
            Route::put('order', ReorderBankAccountsController::class)->middleware([$bankAccounts, 'throttle:sensitive'])->name('order');

            Route::whereUuid('bankAccount')->group(function () use ($bankAccounts): void {
                Route::patch('{bankAccount}', [BankAccountController::class, 'update'])->middleware([$bankAccounts, 'throttle:sensitive'])->name('update');
                Route::delete('{bankAccount}', [BankAccountController::class, 'destroy'])->middleware([$bankAccounts, 'throttle:sensitive'])->name('destroy');
                Route::post('{bankAccount}/activate', ActivateBankAccountController::class)->middleware([$bankAccounts, 'throttle:sensitive'])->name('activate');
                Route::post('{bankAccount}/deactivate', DeactivateBankAccountController::class)->middleware([$bankAccounts, 'throttle:sensitive'])->name('deactivate');
            });
        });
    });
});

Route::get('content/assessment-paths/{path}', PathGuideController::class)
    ->whereIn('path', PathSetting::pathValues())
    ->middleware('throttle:public')
    ->name('content.assessment-paths.show');
