<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Http\Controllers\Api\V1\Integrations\AiModelController;
use App\Http\Controllers\Api\V1\Integrations\AiSettingsController;
use App\Http\Controllers\Api\V1\Integrations\AiTestAnswerController;
use App\Http\Controllers\Api\V1\Integrations\ApplePayAssociationFileController;
use App\Http\Controllers\Api\V1\Integrations\CheckApplePayAssociationFileController;
use App\Http\Controllers\Api\V1\Integrations\EmailSettingsController;
use App\Http\Controllers\Api\V1\Integrations\IntegrationOverviewController;
use App\Http\Controllers\Api\V1\Integrations\MsegatTemplateController;
use App\Http\Controllers\Api\V1\Integrations\PaymentSettingsController;
use App\Http\Controllers\Api\V1\Integrations\RegisterPaymentWebhookController;
use App\Http\Controllers\Api\V1\Integrations\SendTestEmailController;
use App\Http\Controllers\Api\V1\Integrations\SendTestSmsController;
use App\Http\Controllers\Api\V1\Integrations\SmsSettingsController;
use App\Http\Controllers\Api\V1\Integrations\TaqnyatSenderController;
use App\Http\Controllers\Api\V1\Integrations\TestAiConnectionController;
use App\Http\Controllers\Api\V1\Integrations\TestEmailConnectionController;
use App\Http\Controllers\Api\V1\Integrations\TestPaymentConnectionController;
use App\Http\Controllers\Api\V1\Integrations\TestSmsConnectionController;
use Illuminate\Support\Facades\Route;

$payment = 'can:'.Ability::ManagePaymentIntegration->value;
$sms = 'can:'.Ability::ManageSmsIntegration->value;
$email = 'can:'.Ability::ManageEmailIntegration->value;
$ai = 'can:'.Ability::ManageAiIntegration->value;

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'])->prefix('settings')->name('settings.')->group(function () use ($payment, $sms, $email, $ai): void {
    Route::get('integrations', IntegrationOverviewController::class)->middleware('can:'.Ability::IntegrationsOverview->value)->name('integrations.index');

    Route::prefix('payment')->name('payment.')->group(function () use ($payment): void {
        Route::get('/', [PaymentSettingsController::class, 'show'])->middleware($payment)->name('show');
        Route::put('/', [PaymentSettingsController::class, 'update'])->middleware([$payment, 'throttle:sensitive'])->name('update');
        Route::post('test-connection', TestPaymentConnectionController::class)->middleware([$payment, 'throttle:integration-probe'])->name('test-connection');
        Route::post('webhook/register', RegisterPaymentWebhookController::class)->middleware([$payment, 'throttle:integration-probe'])->name('webhook.register');
        Route::post('apple-pay/association-file', [ApplePayAssociationFileController::class, 'store'])->middleware([$payment, 'throttle:sensitive'])->name('apple-pay.file.store');
        Route::delete('apple-pay/association-file', [ApplePayAssociationFileController::class, 'destroy'])->middleware([$payment, 'throttle:sensitive'])->name('apple-pay.file.destroy');
        Route::post('apple-pay/association-file/check', CheckApplePayAssociationFileController::class)->middleware([$payment, 'throttle:integration-probe'])->name('apple-pay.file.check');
    });

    Route::prefix('sms')->name('sms.')->group(function () use ($sms): void {
        Route::get('/', [SmsSettingsController::class, 'show'])->middleware($sms)->name('show');
        Route::put('/', [SmsSettingsController::class, 'update'])->middleware([$sms, 'throttle:sensitive'])->name('update');
        Route::post('test', SendTestSmsController::class)->middleware([$sms, 'throttle:integration-probe'])->name('test');
        Route::get('taqnyat/senders', TaqnyatSenderController::class)->middleware([$sms, 'throttle:integration-probe'])->name('taqnyat.senders');
        Route::get('msegat/templates', MsegatTemplateController::class)->middleware([$sms, 'throttle:integration-probe'])->name('msegat.templates');
        Route::post('{provider}/test-connection', TestSmsConnectionController::class)->whereIn('provider', ['taqnyat', 'msegat'])->middleware([$sms, 'throttle:integration-probe'])->name('test-connection');
    });

    Route::prefix('email')->name('email.')->group(function () use ($email): void {
        Route::get('/', [EmailSettingsController::class, 'show'])->middleware($email)->name('show');
        Route::put('/', [EmailSettingsController::class, 'update'])->middleware([$email, 'throttle:sensitive'])->name('update');
        Route::post('test-connection', TestEmailConnectionController::class)->middleware([$email, 'throttle:integration-probe'])->name('test-connection');
        Route::post('test', SendTestEmailController::class)->middleware([$email, 'throttle:integration-probe'])->name('test');
    });

    Route::prefix('ai')->name('ai.')->group(function () use ($ai): void {
        Route::get('/', [AiSettingsController::class, 'show'])->middleware($ai)->name('show');
        Route::put('/', [AiSettingsController::class, 'update'])->middleware([$ai, 'throttle:sensitive'])->name('update');
        Route::get('models', AiModelController::class)->middleware([$ai, 'throttle:integration-probe'])->name('models');
        Route::post('test-connection', TestAiConnectionController::class)->middleware([$ai, 'throttle:integration-probe'])->name('test-connection');
        Route::post('test-answer', AiTestAnswerController::class)->middleware([$ai, 'throttle:integration-probe'])->name('test-answer');
    });
});
