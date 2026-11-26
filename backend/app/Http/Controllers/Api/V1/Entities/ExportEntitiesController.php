<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ExportEntities;
use App\Http\Requests\Entities\IndexEntitiesRequest;
use App\Http\Responses\CsvDownloadResponse;
use App\Models\Identity\User;
use App\Services\Entities\EntityCsvWriter;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportEntitiesController
{
    public function __invoke(IndexEntitiesRequest $request, #[CurrentUser] User $actor, ExportEntities $exportEntities, EntityCsvWriter $writer, CsvDownloadResponse $csv): StreamedResponse
    {
        $export = $exportEntities->handle($actor, $request->filters(), app()->getLocale());

        return $csv->stream($export->fileName, $export->rowCount, static function ($stream) use ($writer, $export): void {
            $writer->write($export, $stream);
        });
    }
}
