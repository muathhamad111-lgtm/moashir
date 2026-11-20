<?php

declare(strict_types=1);

use App\Data\Watermark\StampSheet;
use App\Integrations\Fakes\FakeStampPrinter;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Support\ValueObjects\PageSize;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

const PPTX_MIME = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

/**
 * @param  list<PageSize>  $sizes
 */
function multiPagePdf(array $sizes, string $text = 'ORIGINAL EVIDENCE CONTENT'): string
{
    return new FakeStampPrinter()->print(new StampSheet('', $sizes, $text));
}

function solidImage(string $type, int $width = 240, int $height = 160): string
{
    $canvas = imagecreatetruecolor($width, $height);
    imagefilledrectangle($canvas, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($canvas, 250, 250, 250));
    $stream = fopen('php://memory', 'w+b');
    $type === 'png' ? imagepng($canvas, $stream) : imagejpeg($canvas, $stream, 95);
    rewind($stream);

    return (string) stream_get_contents($stream);
}

function watermarkFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/watermark/'.$name);
}

function replaceEvidenceContent(Assessment $assessment, string $name, string $mimeType, string $contents): EvidenceFile
{
    $evidence = EvidenceFile::query()->with('storedFile')->where('assessment_id', $assessment->id)->firstOrFail();
    $file = $evidence->storedFile;
    Storage::disk($file->disk)->put($file->path, $contents);
    $file->forceFill([
        'original_name' => $name,
        'extension' => pathinfo($name, PATHINFO_EXTENSION),
        'mime_type' => $mimeType,
        'size_bytes' => strlen($contents),
        'sha256' => hash('sha256', $contents),
    ])->save();

    return $evidence->refresh();
}

function storedContentOf(StoredFile $file): string
{
    return (string) Storage::disk($file->disk)->get($file->path);
}

function downloadEvidenceAs(User $viewer, Assessment $assessment, EvidenceFile $evidence, string $intent = 'download'): TestResponse
{
    $url = (string) switchTo($viewer)->postJson("/api/v1/assessments/{$assessment->id}/evidence/{$evidence->public_id}/download")->assertOk()->json('data.url');

    return test()->get($url.'&intent='.$intent);
}

function pdfTextOf(string $pdf): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'moashir-test-pdf-');
    file_put_contents($path, $pdf);

    try {
        return Process::run(['pdftotext', '-layout', $path, '-'])->output();
    } finally {
        unlink($path);
    }
}

function pdfPageCountOf(string $pdf): int
{
    $path = (string) tempnam(sys_get_temp_dir(), 'moashir-test-pdf-');
    file_put_contents($path, $pdf);

    try {
        preg_match('/^Pages:\s+(\d+)/m', Process::run(['pdfinfo', $path])->output(), $match);

        return (int) ($match[1] ?? 0);
    } finally {
        unlink($path);
    }
}

function passwordProtectedPdf(): string
{
    $source = (string) tempnam(sys_get_temp_dir(), 'moashir-test-pdf-');
    $target = $source.'-locked.pdf';
    file_put_contents($source, multiPagePdf([new PageSize(595, 842)]));

    try {
        Process::run(['qpdf', '--encrypt', 'reader-secret', 'owner-secret', '256', '--', $source, $target]);

        return (string) file_get_contents($target);
    } finally {
        foreach ([$source, $target] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
