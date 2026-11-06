<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Audit;

use App\Actions\Audit\ExportAuditLogs;
use App\Http\Requests\Audit\ExportAuditLogsRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditLogCsvWriter;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportAuditLogsController
{
    public function __invoke(ExportAuditLogsRequest $request, #[CurrentUser] User $viewer, ExportAuditLogs $exportAuditLogs, AuditLogCsvWriter $writer): StreamedResponse
    {
        $export = $exportAuditLogs->handle($viewer, $request->filters(), $request->reason());

        return response()->streamDownload(static function () use ($writer, $export): void {
            $stream = fopen('php://output', 'wb');

            if ($stream !== false) {
                $writer->write($export, $stream);
                fclose($stream);
            }
        }, $export->fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Row-Count' => (string) $export->rowCount,
        ]);
    }
}
