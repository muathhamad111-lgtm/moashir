<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ExportTransactions;
use App\Http\Requests\Finance\IndexTransactionsRequest;
use App\Http\Resources\Finance\FinanceExportResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\CsvDownloadResponse;
use App\Models\Identity\User;
use App\Services\Finance\TransactionCsvWriter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportTransactionsController
{
    public function __invoke(IndexTransactionsRequest $request, #[CurrentUser] User $actor, ExportTransactions $exportTransactions, TransactionCsvWriter $writer, CsvDownloadResponse $csv): JsonResponse|StreamedResponse
    {
        $export = $exportTransactions->handle($actor, $request->filters(), app()->getLocale());
        $payments = $export->payments;

        if (! $payments instanceof LazyCollection) {
            return ApiResponse::resource(new FinanceExportResource($export->export), Response::HTTP_ACCEPTED);
        }

        $locale = $export->export->locale;

        return $csv->stream($export->fileName, $export->export->row_count, static function ($stream) use ($writer, $payments, $locale): void {
            $writer->write($payments, $locale, $stream);
        });
    }
}
