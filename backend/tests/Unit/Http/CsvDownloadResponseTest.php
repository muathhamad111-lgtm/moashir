<?php

declare(strict_types=1);

use App\Contracts\Platform\DownloadStreamOpener;
use App\Exceptions\Platform\ExportStreamUnavailable;
use App\Http\Responses\CsvDownloadResponse;
use Illuminate\Testing\TestResponse;

function csvDownloadWith(DownloadStreamOpener $opener): CsvDownloadResponse
{
    app()->instance(DownloadStreamOpener::class, $opener);

    return app(CsvDownloadResponse::class);
}

function streamOpenerReturning(mixed $stream): DownloadStreamOpener
{
    return new readonly class($stream) implements DownloadStreamOpener
    {
        public function __construct(private mixed $stream) {}

        public function open(): mixed
        {
            return $this->stream;
        }
    };
}

it('streams the written rows as a CSV attachment with the row count', function (): void {
    $response = app(CsvDownloadResponse::class)->stream('entities-2026-11-18.csv', 2, static function ($stream): void {
        fwrite($stream, "reference,name\nENT-0001,Al-Amal\n");
    });

    $download = TestResponse::fromBaseResponse($response);

    expect($download->streamedContent())->toBe("reference,name\nENT-0001,Al-Amal\n")
        ->and($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
        ->and($response->headers->get('X-Export-Row-Count'))->toBe('2')
        ->and($response->headers->get('Content-Disposition'))->toContain('entities-2026-11-18.csv');
});

it('refuses with a named error before any byte is sent when the stream cannot be opened', function (): void {
    $writerCalled = false;

    expect(fn (): mixed => csvDownloadWith(streamOpenerReturning(false))->stream('users.csv', 1, static function () use (&$writerCalled): void {
        $writerCalled = true;
    }))->toThrow(ExportStreamUnavailable::class);

    expect($writerCalled)->toBeFalse();
});

it('renders the unavailable stream as a safe translated service error', function (): void {
    $error = ExportStreamUnavailable::cannotOpen()->error();

    expect($error->status)->toBe(503)
        ->and($error->code)->toBe('export.stream_unavailable')
        ->and($error->message())->toBe('تعذر تجهيز ملف التصدير. حاول مرة أخرى لاحقًا.');
});

it('closes the stream even when the writer fails part way', function (): void {
    $stream = fopen('php://memory', 'wb');
    $response = csvDownloadWith(streamOpenerReturning($stream))->stream('audit.csv', 1, static function (): void {
        throw new RuntimeException('writer failed');
    });

    expect(fn (): mixed => $response->sendContent())->toThrow(RuntimeException::class, 'writer failed')
        ->and(is_resource($stream))->toBeFalse();
});
