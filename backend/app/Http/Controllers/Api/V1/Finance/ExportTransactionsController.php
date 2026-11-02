<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ExportTransactions;
use App\Http\Requests\Finance\IndexTransactionsRequest;
use App\Http\Resources\Finance\FinanceExportResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Finance\TransactionCsvWriter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportTransactionsController
{
    public function __invoke(IndexTransactionsRequest $request, #[CurrentUser] User $actor, ExportTransactions $exportTransactions, TransactionCsvWriter $writer): JsonResponse|StreamedResponse
    {
        $export = $exportTransactions->handle($actor, $request->filters(), app()->getLocale());
        $payments = $export->payments;

        if (! $payments instanceof LazyCollection) {
            return ApiResponse::resource(new FinanceExportResource($export->export), Response::HTTP_ACCEPTED);
        }

        $locale = $export->export->locale;

        return response()->streamDownload(static function () use ($writer, $payments, $locale): void {
            $stream = fopen('php://output', 'wb');

            if ($stream !== false) {
                $writer->write($payments, $locale, $stream);
                fclose($stream);
            }
        }, $export->fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Row-Count' => (string) $export->export->row_count,
        ]);
    }
}
