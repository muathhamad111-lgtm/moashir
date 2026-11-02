<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Documents\PdfRenderer;
use App\Data\Files\GeneratedFile;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Files\StoredFile;
use App\Models\Finance\PaymentReceipt;
use App\Services\Files\StoredFileRemover;
use App\Services\Files\StoredFileWriter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ReceiptPdfPublisher
{
    private const string MIME_TYPE = 'application/pdf';

    public function __construct(
        private PdfRenderer $renderer,
        private ReceiptDocumentBuilder $documents,
        private StoredFileWriter $writer,
        private StoredFileRemover $remover,
    ) {}

    public function ensure(PaymentReceipt $receipt): StoredFile
    {
        $current = $this->currentFileOf($receipt);

        if ($current instanceof StoredFile) {
            return $current;
        }

        $rendered = $this->render($receipt);
        $kept = DB::transaction(fn (): ?StoredFile => $this->attachIfMissing($receipt, $rendered));

        if ($kept instanceof StoredFile) {
            $this->remover->remove($rendered);

            return $kept;
        }

        return $rendered;
    }

    public function regenerate(PaymentReceipt $receipt): StoredFile
    {
        $rendered = $this->render($receipt);

        DB::transaction(function () use ($receipt, $rendered): void {
            $locked = PaymentReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'pdf_file_id' => $rendered->id,
                'reissue_count' => $locked->reissue_count + 1,
                'last_reissued_at' => Date::now(),
            ])->save();
        });

        $receipt->refresh();

        return $rendered;
    }

    private function render(PaymentReceipt $receipt): StoredFile
    {
        return $this->writer->storeGenerated(new GeneratedFile(
            StoredFilePurpose::ReceiptPdf,
            $this->renderer->render($this->documents->for($receipt)),
            $this->documents->fileNameOf($receipt),
            'pdf',
            self::MIME_TYPE,
        ), $receipt);
    }

    private function attachIfMissing(PaymentReceipt $receipt, StoredFile $rendered): ?StoredFile
    {
        $locked = PaymentReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
        $existing = $this->currentFileOf($locked);

        if ($existing instanceof StoredFile) {
            return $existing;
        }

        $locked->forceFill(['pdf_file_id' => $rendered->id])->save();
        $receipt->setRawAttributes($locked->getAttributes(), true);

        return null;
    }

    private function currentFileOf(PaymentReceipt $receipt): ?StoredFile
    {
        return $receipt->pdf_file_id === null ? null : StoredFile::query()->whereKey($receipt->pdf_file_id)->first();
    }
}
