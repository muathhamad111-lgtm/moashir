<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ExportEntities;
use App\Http\Requests\Entities\IndexEntitiesRequest;
use App\Models\Identity\User;
use App\Services\Entities\EntityCsvWriter;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportEntitiesController
{
    public function __invoke(IndexEntitiesRequest $request, #[CurrentUser] User $actor, ExportEntities $exportEntities, EntityCsvWriter $writer): StreamedResponse
    {
        $export = $exportEntities->handle($actor, $request->filters(), app()->getLocale());

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
