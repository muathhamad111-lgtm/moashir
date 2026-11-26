<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Audit;

use App\Actions\Audit\ExportAuditLogs;
use App\Http\Requests\Audit\ExportAuditLogsRequest;
use App\Http\Responses\CsvDownloadResponse;
use App\Models\Identity\User;
use App\Services\Audit\AuditLogCsvWriter;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportAuditLogsController
{
    public function __invoke(ExportAuditLogsRequest $request, #[CurrentUser] User $viewer, ExportAuditLogs $exportAuditLogs, AuditLogCsvWriter $writer, CsvDownloadResponse $csv): StreamedResponse
    {
        $export = $exportAuditLogs->handle($viewer, $request->filters(), $request->reason());

        return $csv->stream($export->fileName, $export->rowCount, static function ($stream) use ($writer, $export): void {
            $writer->write($export, $stream);
        });
    }
}
