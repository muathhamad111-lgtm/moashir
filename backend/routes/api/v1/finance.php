<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Files\DownloadStoredFileController;
use App\Http\Controllers\Api\V1\Finance\AbandonedCartController;
use App\Http\Controllers\Api\V1\Finance\BankTransferAccountController;
use App\Http\Controllers\Api\V1\Finance\BankTransferController;
use App\Http\Controllers\Api\V1\Finance\BankTransferDecisionController;
use App\Http\Controllers\Api\V1\Finance\BulkBankTransferDecisionController;
use App\Http\Controllers\Api\V1\Finance\CancelPaymentController;
use App\Http\Controllers\Api\V1\Finance\CheckoutOptionsController;
use App\Http\Controllers\Api\V1\Finance\ExportTransactionsController;
use App\Http\Controllers\Api\V1\Finance\FinanceExportController;
use App\Http\Controllers\Api\V1\Finance\FinanceReceiptController;
use App\Http\Controllers\Api\V1\Finance\FinanceSummaryController;
use App\Http\Controllers\Api\V1\Finance\ManualSettlementController;
use App\Http\Controllers\Api\V1\Finance\MoyasarWebhookController;
use App\Http\Controllers\Api\V1\Finance\PaymentController;
use App\Http\Controllers\Api\V1\Finance\PaymentEventController;
use App\Http\Controllers\Api\V1\Finance\PaymentReceiptController;
use App\Http\Controllers\Api\V1\Finance\RefundController;
use App\Http\Controllers\Api\V1\Finance\ServicePricesController;
use App\Http\Controllers\Api\V1\Finance\TransactionController;
use App\Http\Controllers\Api\V1\Finance\TransferProofController;
use App\Http\Controllers\Api\V1\Finance\VerifyPaymentController;
use App\Http\Middleware\EnsureJsonRequest;
use App\Http\Middleware\ValidateDownloadSignature;
use App\Models\Finance\CheckoutIntent;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

$payments = Payment::class;
$signedIn = ['auth:sanctum', 'session.timeouts', 'mfa', 'password.current'];

Route::post('webhooks/moyasar', MoyasarWebhookController::class)
    ->withoutMiddleware([EnsureJsonRequest::class, EnsureFrontendRequestsAreStateful::class])
    ->middleware(['payload.limit:'.config()->integer('moashir.finance.webhook_max_bytes'), 'throttle:webhooks'])
    ->name('webhooks.moyasar');

Route::get('content/prices', ServicePricesController::class)->middleware('throttle:public')->name('content.prices');

Route::middleware($signedIn)->group(function () use ($payments): void {
    Route::prefix('payments')->name('payments.')->group(function () use ($payments): void {
        Route::get('mine', [PaymentController::class, 'index'])->middleware("can:viewAnyOwn,{$payments}")->name('mine');
        Route::post('/', [PaymentController::class, 'store'])->middleware(["can:create,{$payments}", 'throttle:sensitive'])->name('store');
        Route::get('checkout-options', CheckoutOptionsController::class)->middleware("can:create,{$payments}")->name('checkout-options');

        Route::whereUuid('payment')->group(function (): void {
            Route::get('{payment}', [PaymentController::class, 'show'])->middleware('can:viewOwn,payment')->name('show');
            Route::post('{payment}/verify', VerifyPaymentController::class)->middleware(['can:verify,payment', 'throttle:payment-verify'])->name('verify');
            Route::get('{payment}/receipt', [PaymentReceiptController::class, 'show'])->middleware('can:viewOwn,payment')->name('receipt.show');
            Route::post('{payment}/receipt/download', [PaymentReceiptController::class, 'download'])->middleware(['can:viewOwn,payment', 'throttle:downloads'])->name('receipt.download');
            Route::post('{payment}/cancel', CancelPaymentController::class)->middleware(['can:cancel,payment', 'throttle:sensitive'])->name('cancel');
            Route::post('{payment}/bank-transfer/proof/download', [TransferProofController::class, 'own'])->middleware(['can:downloadOwnTransferProof,payment', 'throttle:downloads'])->name('bank-transfer.proof.download');
        });
    });

    Route::prefix('finance')->name('finance.')->group(function () use ($payments): void {
        Route::get('summary', FinanceSummaryController::class)->middleware("can:viewOperations,{$payments}")->name('summary');
        Route::get('transactions', [TransactionController::class, 'index'])->middleware("can:viewOperations,{$payments}")->name('transactions.index');
        Route::post('transactions/export', ExportTransactionsController::class)->middleware(["can:export,{$payments}", 'throttle:exports'])->name('transactions.export');
        Route::get('transactions/{payment}', [TransactionController::class, 'show'])->whereUuid('payment')->middleware("can:viewOperations,{$payments}")->name('transactions.show');
        Route::get('payment-events', PaymentEventController::class)->middleware("can:viewEvents,{$payments}")->name('payment-events.index');

        Route::prefix('bank-transfers')->name('bank-transfers.')->group(function () use ($payments): void {
            Route::get('/', [BankTransferController::class, 'index'])->middleware("can:viewTransfers,{$payments}")->name('index');
            Route::get('accounts', BankTransferAccountController::class)->middleware("can:viewTransfers,{$payments}")->name('accounts.index');
            Route::post('bulk-approve', [BulkBankTransferDecisionController::class, 'approve'])->middleware(["can:reviewTransfers,{$payments}", 'throttle:sensitive'])->name('bulk-approve');
            Route::post('bulk-reject', [BulkBankTransferDecisionController::class, 'reject'])->middleware(["can:reviewTransfers,{$payments}", 'throttle:sensitive'])->name('bulk-reject');

            Route::whereUuid('bankTransfer')->group(function () use ($payments): void {
                Route::get('{bankTransfer}', [BankTransferController::class, 'show'])->middleware("can:viewTransfers,{$payments}")->name('show');
                Route::post('{bankTransfer}/proof/download', [TransferProofController::class, 'staff'])->middleware(["can:viewTransfers,{$payments}", 'throttle:downloads'])->name('proof.download');
                Route::post('{bankTransfer}/approve', [BankTransferDecisionController::class, 'approve'])->middleware(["can:reviewTransfers,{$payments}", 'throttle:sensitive'])->name('approve');
                Route::post('{bankTransfer}/reject', [BankTransferDecisionController::class, 'reject'])->middleware(["can:reviewTransfers,{$payments}", 'throttle:sensitive'])->name('reject');
            });
        });

        Route::whereUuid(['payment', 'receipt', 'refund', 'financeExport'])->group(function (): void {
            Route::post('payments/{payment}/refunds', [RefundController::class, 'store'])->middleware(['can:recordRefund,payment', 'throttle:sensitive'])->name('refunds.store');
            Route::post('payments/{payment}/manual-settle', ManualSettlementController::class)->middleware(['can:manualSettle,payment', 'throttle:sensitive'])->name('payments.manual-settle');
            Route::get('refunds', [RefundController::class, 'index'])->middleware('can:viewAny,'.Refund::class)->name('refunds.index');
            Route::get('refunds/{refund}', [RefundController::class, 'show'])->middleware('can:view,refund')->name('refunds.show');
            Route::post('refunds/{refund}/evidence/download', [RefundController::class, 'downloadEvidence'])->middleware(['can:downloadEvidence,refund', 'throttle:downloads'])->name('refunds.evidence.download');
            Route::post('receipts/{receipt}/download', [FinanceReceiptController::class, 'download'])->middleware(['can:download,receipt', 'throttle:downloads'])->name('receipts.download');
            Route::post('receipts/{receipt}/reissue', [FinanceReceiptController::class, 'reissue'])->middleware(['can:reissue,receipt', 'throttle:sensitive'])->name('receipts.reissue');
            Route::get('exports/{financeExport}', [FinanceExportController::class, 'show'])->middleware('can:view,financeExport')->name('exports.show');
            Route::post('exports/{financeExport}/download', [FinanceExportController::class, 'download'])->middleware(['can:download,financeExport', 'throttle:downloads'])->name('exports.download');
        });
    });

    Route::get('messaging/abandoned-carts', AbandonedCartController::class)->middleware('can:viewAny,'.CheckoutIntent::class)->name('messaging.abandoned-carts.index');

    Route::get('files/{file}/download', DownloadStoredFileController::class)
        ->whereUuid('file')
        ->withoutMiddleware([EnsureJsonRequest::class])
        ->middleware([ValidateDownloadSignature::class, 'can:download,file', 'throttle:downloads'])
        ->name('files.download');
});
