<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Finance\ListRefunds;
use App\Contracts\Finance\LinkedRequestReader;
use App\Contracts\Finance\OpenedRequestLookup;
use App\Contracts\Finance\ServicePriceSource;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Files\StoredFile;
use App\Models\Finance\BankAccount;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\FinanceExport;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Finance\Refund;
use App\Services\Assessments\AccreditedPurchaseCheck;
use App\Services\Assessments\LinkedRequestByPayment;
use App\Services\Assessments\OpenedRequestsByPayment;
use App\Services\Entities\EntityEraser;
use App\Services\Entities\OpenRequestDirectory;
use App\Services\Finance\BankAccountCounts;
use App\Services\Finance\BankTransferEraser;
use App\Services\Finance\BankTransferQuery;
use App\Services\Finance\CheckoutIntentEraser;
use App\Services\Finance\OpenBankTransfers;
use App\Services\Finance\PurchaseEligibility;
use App\Services\Finance\SettingsServicePriceSource;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ServicePriceSource::class, SettingsServicePriceSource::class);
        $this->app->bind(OpenedRequestLookup::class, OpenedRequestsByPayment::class);
        $this->app->bind(LinkedRequestReader::class, LinkedRequestByPayment::class);
        $this->app->tag([CheckoutIntentEraser::class, BankTransferEraser::class], EntityEraser::TAG);
        $this->app->tag([OpenBankTransfers::class], OpenRequestDirectory::TAG);
        $this->app->tag([AccreditedPurchaseCheck::class], PurchaseEligibility::TAG);
    }

    public function boot(): void
    {
        Route::bind('payment', static fn (string $value): Payment => Payment::query()
            ->with(['entity', 'receipt'])
            ->where('public_id', $value)
            ->firstOrFail());

        Route::bind('bankTransfer', static fn (string $value): Payment => Payment::query()
            ->bankTransfers()
            ->with(BankTransferQuery::RELATIONS)
            ->where('public_id', $value)
            ->firstOrFail());

        Route::bind('bankAccount', static fn (string $value): BankAccount => BankAccount::query()
            ->listed()
            ->with('createdBy')
            ->withCount(BankAccountCounts::relations())
            ->where('public_id', $value)
            ->firstOrFail());

        Route::bind('receipt', static fn (string $value): PaymentReceipt => PaymentReceipt::query()
            ->with('payment')
            ->where('public_id', $value)
            ->firstOrFail());

        Route::bind('refund', static fn (string $value): Refund => Refund::query()
            ->with(ListRefunds::RELATIONS)
            ->where('public_id', $value)
            ->firstOrFail());

        Route::bind('financeExport', static fn (string $value): FinanceExport => FinanceExport::query()
            ->with('storedFile')
            ->where('public_id', $value)
            ->firstOrFail());

        Route::bind('file', static fn (string $value): StoredFile => StoredFile::query()
            ->with(['attachable' => static fn (MorphTo $owner): MorphTo => $owner->morphWith([
                PaymentReceipt::class => ['payment'],
                BankTransfer::class => ['payment'],
                Refund::class => ['payment'],
                EvidenceFile::class => ['assessment.entity', 'assessment.activeAssignment'],
                EvidenceBundle::class => ['assessment.entity', 'assessment.activeAssignment'],
            ])])
            ->where('public_id', $value)
            ->firstOrFail());
    }
}
