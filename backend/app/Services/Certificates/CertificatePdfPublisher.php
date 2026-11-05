<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Contracts\Documents\PdfRenderer;
use App\Data\Files\GeneratedFile;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Certificates\Certificate;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileRemover;
use App\Services\Files\StoredFileWriter;
use Illuminate\Support\Facades\DB;

final readonly class CertificatePdfPublisher
{
    private const string MIME_TYPE = 'application/pdf';

    public function __construct(
        private PdfRenderer $renderer,
        private CertificateDocumentBuilder $documents,
        private StoredFileWriter $writer,
        private StoredFileRemover $remover,
    ) {}

    public function ensure(Certificate $certificate): StoredFile
    {
        $current = $this->currentFileOf($certificate);

        if ($current instanceof StoredFile) {
            return $current;
        }

        $rendered = $this->writer->storeGenerated(new GeneratedFile(
            StoredFilePurpose::CertificatePdf,
            $this->renderer->render($this->documents->for($certificate)),
            $this->documents->fileNameOf($certificate),
            'pdf',
            self::MIME_TYPE,
        ), $certificate);

        $kept = DB::transaction(fn (): ?StoredFile => $this->attachIfMissing($certificate, $rendered));

        if ($kept instanceof StoredFile) {
            $this->remover->remove($rendered);

            return $kept;
        }

        return $rendered;
    }

    private function attachIfMissing(Certificate $certificate, StoredFile $rendered): ?StoredFile
    {
        $locked = Certificate::query()->whereKey($certificate->id)->lockForUpdate()->firstOrFail();
        $existing = $this->currentFileOf($locked);

        if ($existing instanceof StoredFile) {
            return $existing;
        }

        $locked->forceFill(['pdf_file_id' => $rendered->id])->save();
        $certificate->setRawAttributes($locked->getAttributes(), true);

        return null;
    }

    private function currentFileOf(Certificate $certificate): ?StoredFile
    {
        return $certificate->pdf_file_id === null ? null : StoredFile::query()->whereKey($certificate->pdf_file_id)->first();
    }
}
