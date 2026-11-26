<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Nda;

use App\Actions\Nda\ExportNdaAcceptances;
use App\Http\Requests\Nda\IndexNdaAcceptancesRequest;
use App\Http\Responses\CsvDownloadResponse;
use App\Models\Identity\User;
use App\Services\Nda\NdaAcceptanceCsvWriter;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportNdaAcceptancesController
{
    public function __invoke(IndexNdaAcceptancesRequest $request, #[CurrentUser] User $actor, ExportNdaAcceptances $export, NdaAcceptanceCsvWriter $writer, CsvDownloadResponse $csv): StreamedResponse
    {
        $register = $export->handle($actor, $request->filter(), app()->getLocale());

        return $csv->stream($register->fileName, $register->rowCount, static function ($stream) use ($writer, $register): void {
            $writer->write($register, $stream);
        });
    }
}
