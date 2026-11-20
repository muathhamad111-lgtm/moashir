<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use App\Contracts\Watermark\OfficeConverter;
use App\Data\Files\GeneratedFile;
use App\Enums\Files\RenditionStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Exceptions\Watermark\WatermarkFailed;
use App\Jobs\Files\ConvertOfficeFileJob;
use App\Models\Files\FileRendition;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileWriter;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class OfficeRenditions
{
    private const string PDF_MIME_TYPE = 'application/pdf';

    private const string FAILURE_CODE = 'conversion_failed';

    public function __construct(
        private OfficeConverter $converter,
        private StoredFileWriter $writer,
        private WatermarkSourceCopier $copier,
        private WatermarkSlots $slots,
    ) {}

    public function pdfInto(StoredFile $source, WatermarkWorkspace $workspace): string
    {
        $rendition = $this->existingFor($source);

        if (! $rendition instanceof FileRendition) {
            ConvertOfficeFileJob::dispatch($source->id);

            throw WatermarkFailed::preparing();
        }

        if (! $rendition->isReady() || ! $rendition->rendition instanceof StoredFile) {
            throw WatermarkFailed::conversionFailed();
        }

        return $this->copier->copy($rendition->rendition, $workspace->path('rendition.pdf'));
    }

    public function convertOnce(StoredFile $source, WatermarkWorkspace $workspace): FileRendition
    {
        $existing = $this->existingFor($source);

        if ($existing instanceof FileRendition) {
            return $existing;
        }

        return $this->slots->convert(fn (): FileRendition => $this->convertUnlessDone($source, $workspace));
    }

    private function convertUnlessDone(StoredFile $source, WatermarkWorkspace $workspace): FileRendition
    {
        return $this->existingFor($source) ?? $this->convert($source, $workspace);
    }

    private function existingFor(StoredFile $source): ?FileRendition
    {
        return FileRendition::query()->with('rendition')->where('source_file_id', $source->id)->first();
    }

    private function convert(StoredFile $source, WatermarkWorkspace $workspace): FileRendition
    {
        $original = $this->copier->copy($source, $workspace->path('original.'.($source->extension ?? 'bin')));

        try {
            $pdf = $this->converter->toPdf($original, $workspace->path(''), $workspace->path('office-profile'));
        } catch (WatermarkFailed) {
            return $this->record($source, RenditionStatus::Failed, self::FAILURE_CODE);
        }

        $rendition = $this->record($source, RenditionStatus::Ready, null);
        $stored = $this->writer->storeGeneratedFromPath(
            new GeneratedFile(StoredFilePurpose::OfficeRendition, '', pathinfo($source->original_name, PATHINFO_FILENAME).'.pdf', 'pdf', self::PDF_MIME_TYPE),
            $pdf,
            $rendition,
        );
        $rendition->forceFill(['rendition_file_id' => $stored->id])->save();

        return $rendition->setRelation('rendition', $stored);
    }

    private function record(StoredFile $source, RenditionStatus $status, ?string $failureCode): FileRendition
    {
        try {
            return FileRendition::query()->create(['source_file_id' => $source->id, 'status' => $status, 'failure_code' => $failureCode]);
        } catch (UniqueConstraintViolationException) {
            throw WatermarkFailed::conversionFailed();
        }
    }
}
