<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use App\Data\Audit\AuditEntry;
use App\Data\Watermark\StampedFile;
use App\Data\Watermark\ViewerStamp;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\AccessIntent;
use App\Enums\Files\WatermarkFormat;
use App\Exceptions\Watermark\WatermarkFailed;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\FileAccessIdentityGate;
use App\Support\Identity\AccountNationalId;
use App\Support\ValueObjects\TraceCode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;

final readonly class FileStamper
{
    public function __construct(
        private WatermarkSourceCopier $copier,
        private OfficeRenditions $renditions,
        private PdfStamper $pdfs,
        private ImageStamper $images,
        private FileAccessTraces $traces,
        private AuditRecorder $audit,
        private WatermarkSlots $slots,
        private Repository $config,
        private FileAccessIdentityGate $identityGate,
    ) {}

    public function stamp(User $viewer, StoredFile $file, AccessIntent $intent, WatermarkWorkspace $workspace): StampedFile
    {
        // The links are gated when issued; this repeats the check for a link issued before the account became
        // required and for evidence bundles built in the background.
        $this->identityGate->assertMayOpen($viewer, $file->purpose);
        $format = WatermarkFormat::ofMimeType($file->mime_type) ?? throw WatermarkFailed::unreadable();

        if ($file->size_bytes > $this->config->integer('moashir.watermark.max_source_bytes')) {
            throw WatermarkFailed::tooLarge();
        }

        $stamp = new ViewerStamp($viewer->name, AccountNationalId::of($viewer), TraceCode::generate(), CarbonImmutable::now(), $viewer->locale);
        $path = $this->slots->stamp(fn (): string => $this->stampedPathOf($file, $format, $stamp, $workspace));

        $this->traces->record($viewer, $file, $intent, $stamp->traceCode);
        $this->audit->record(new AuditEntry(AuditEvent::FileAccessed, $file, [], [
            'purpose' => $file->purpose->value,
            'attachable_type' => $file->attachable_type,
            'attachable_id' => $file->attachable_id,
            'intent' => $intent->value,
            'trace_code' => $stamp->traceCode->value,
            'delivered_as' => $format->deliveredMimeType(),
        ], ['download', 'watermark']));

        return new StampedFile($path, $this->deliveredNameOf($file, $format), $format->deliveredMimeType());
    }

    private function stampedPathOf(StoredFile $file, WatermarkFormat $format, ViewerStamp $stamp, WatermarkWorkspace $workspace): string
    {
        if ($format->isImage()) {
            $source = $this->copier->copy($file, $workspace->path('source.'.$format->value));

            return $this->images->stamp($source, $format === WatermarkFormat::Png, $stamp, $workspace);
        }

        $source = $format === WatermarkFormat::Office
            ? $this->renditions->pdfInto($file, $workspace)
            : $this->copier->copy($file, $workspace->path('source.pdf'));
        $output = $workspace->path('stamped.pdf');
        $this->pdfs->stamp($source, $stamp, $workspace->path('stamp.pdf'), $output);

        return $output;
    }

    private function deliveredNameOf(StoredFile $file, WatermarkFormat $format): string
    {
        return $format === WatermarkFormat::Office ? pathinfo($file->original_name, PATHINFO_FILENAME).'.pdf' : $file->original_name;
    }
}
