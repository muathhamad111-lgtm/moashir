<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ExportUsers;
use App\Http\Requests\Identity\IndexUsersRequest;
use App\Models\Identity\User;
use App\Services\Identity\UserCsvWriter;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportUsersController
{
    public function __invoke(IndexUsersRequest $request, #[CurrentUser] User $actor, ExportUsers $exportUsers, UserCsvWriter $writer): StreamedResponse
    {
        $export = $exportUsers->handle($actor, $request->filters(), app()->getLocale());

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
