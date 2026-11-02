<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Files;

use App\Actions\Files\DownloadStoredFile;
use App\Http\Requests\Files\DownloadStoredFileRequest;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadStoredFileController
{
    public function __invoke(DownloadStoredFileRequest $request, StoredFile $file, #[CurrentUser] User $actor, DownloadStoredFile $download): StreamedResponse
    {
        $result = $download->handle($actor, $file, $request->boundUserId());

        return new StreamedResponse(static function () use ($result): void {
            fpassthru($result->stream);
            fclose($result->stream);
        }, StreamedResponse::HTTP_OK, [
            'Content-Type' => $result->mimeType,
            'Content-Length' => (string) $result->sizeBytes,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $result->fileName, $this->asciiNameOf($result->fileName)),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function asciiNameOf(string $fileName): string
    {
        $extension = preg_replace('/[^A-Za-z0-9]/', '', pathinfo($fileName, PATHINFO_EXTENSION)) ?? '';

        return $extension === '' ? 'download' : 'download.'.$extension;
    }
}
