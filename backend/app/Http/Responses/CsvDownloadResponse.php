<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Contracts\Platform\DownloadStreamOpener;
use App\Exceptions\Platform\ExportStreamUnavailable;
use Closure;
use Illuminate\Contracts\Routing\ResponseFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class CsvDownloadResponse
{
    private const string CONTENT_TYPE = 'text/csv; charset=UTF-8';

    public function __construct(private DownloadStreamOpener $opener, private ResponseFactory $responses) {}

    /**
     * @param  Closure(resource): void  $writeRows
     */
    public function stream(string $fileName, int $rowCount, Closure $writeRows): StreamedResponse
    {
        $stream = $this->opener->open();

        if ($stream === false) {
            throw ExportStreamUnavailable::cannotOpen();
        }

        return $this->responses->streamDownload(static function () use ($stream, $writeRows): void {
            try {
                $writeRows($stream);
            } finally {
                fclose($stream);
            }
        }, $fileName, [
            'Content-Type' => self::CONTENT_TYPE,
            'X-Export-Row-Count' => (string) $rowCount,
        ]);
    }
}
