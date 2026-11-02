<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Audit\AuditEntry;
use App\Data\Finance\TransactionFilters;
use App\Data\Finance\TransactionsExport;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\FinanceExportStatus;
use App\Jobs\Finance\GenerateTransactionsExportJob;
use App\Models\Finance\FinanceExport;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\TransactionQuery;
use Illuminate\Support\Facades\Date;

final readonly class ExportTransactions
{
    private const int CHUNK = 500;

    public function __construct(
        private TransactionQuery $query,
        private FinanceSettings $settings,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, TransactionFilters $filters, string $locale): TransactionsExport
    {
        $liveFilters = $filters->forLiveMode();
        $rowCount = $this->query->filtered($liveFilters)->count();
        $isQueued = $rowCount > $this->settings->exportSyncMaxRows();

        $export = FinanceExport::query()->create([
            'requested_by_user_id' => $actor->id,
            'filters' => $liveFilters->toArray(),
            'row_count' => $rowCount,
            'locale' => $locale === 'en' ? 'en' : 'ar',
            'status' => $isQueued ? FinanceExportStatus::Queued : FinanceExportStatus::Streamed,
        ]);

        $this->audit->record(new AuditEntry(AuditEvent::FinanceExported, $export, [], [
            'row_count' => $rowCount,
            'filters' => $liveFilters->toArray(),
            'queued' => $isQueued,
        ], ['export']));

        if ($isQueued) {
            GenerateTransactionsExportJob::dispatch($export->id);

            return new TransactionsExport($export, self::fileName(), null);
        }

        return new TransactionsExport($export, self::fileName(), $this->query->filtered($liveFilters)->with(ListTransactions::RELATIONS)->lazyById(self::CHUNK));
    }

    public static function fileName(): string
    {
        return 'transactions-'.Date::now()->format('Ymd-His').'.csv';
    }
}
