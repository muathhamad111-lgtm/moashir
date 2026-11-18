<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ExportUsers;
use App\Http\Requests\Identity\IndexUsersRequest;
use App\Http\Responses\CsvDownloadResponse;
use App\Models\Identity\User;
use App\Services\Identity\UserCsvWriter;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportUsersController
{
    public function __invoke(IndexUsersRequest $request, #[CurrentUser] User $actor, ExportUsers $exportUsers, UserCsvWriter $writer, CsvDownloadResponse $csv): StreamedResponse
    {
        $export = $exportUsers->handle($actor, $request->filters(), app()->getLocale());

        return $csv->stream($export->fileName, $export->rowCount, static function ($stream) use ($writer, $export): void {
            $writer->write($export, $stream);
        });
    }
}
