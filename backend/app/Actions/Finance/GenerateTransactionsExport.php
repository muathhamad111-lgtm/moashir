<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Files\GeneratedFile;
use App\Data\Finance\TransactionFilters;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Finance\FinanceExportStatus;
use App\Models\Finance\FinanceExport;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Services\Files\StoredFileWriter;
use App\Services\Finance\TransactionCsvWriter;
use App\Services\Finance\TransactionQuery;
use App\Support\Finance\FinanceNotices;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class GenerateTransactionsExport
{
    private const int CHUNK = 500;

    public function __construct(
        private TransactionQuery $query,
        private TransactionCsvWriter $csv,
        private StoredFileWriter $files,
    ) {}

    public function handle(int $exportId): void
    {
        $export = FinanceExport::query()->find($exportId);

        if (! $export instanceof FinanceExport || $export->status !== FinanceExportStatus::Queued) {
            return;
        }

        $file = $this->files->storeGenerated(new GeneratedFile(
            StoredFilePurpose::FinanceExport,
            $this->contents($export),
            ExportTransactions::fileName(),
            'csv',
            'text/csv',
        ), $export);

        DB::transaction(static function () use ($export, $file): void {
            $export->forceFill(['status' => FinanceExportStatus::Ready, 'stored_file_id' => $file->id, 'completed_at' => Date::now()])->save();
        });

        User::query()->find($export->requested_by_user_id)?->notify(new EntityNotification(FinanceNotices::exportReady($export->public_id, $export->row_count)));
    }

    public function markFailed(int $exportId): void
    {
        FinanceExport::query()->whereKey($exportId)->where('status', FinanceExportStatus::Queued)->first()
            ?->forceFill(['status' => FinanceExportStatus::Failed, 'completed_at' => Date::now()])
            ->save();
    }

    private function contents(FinanceExport $export): string
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('The export buffer could not be opened.');
        }

        $payments = $this->query->filtered(TransactionFilters::fromArray($export->filters)->forLiveMode())->with(ListTransactions::RELATIONS)->lazyById(self::CHUNK);
        $this->csv->write($payments, $export->locale, $stream);
        rewind($stream);
        $contents = (string) stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }
}
