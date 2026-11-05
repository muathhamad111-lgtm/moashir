<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\Documents\DocumentPage;
use App\Data\Documents\PdfDocument;
use App\Enums\Documents\DocumentTemplate;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;

final readonly class DocumentHtmlComposer
{
    public function __construct(
        private ViewFactory $views,
        private Translator $translator,
        private DocumentAssets $assets,
        private QrCodeImage $qrCodes,
    ) {}

    public function compose(PdfDocument $document): string
    {
        $view = match (DocumentTemplate::tryFrom($document->template)) {
            DocumentTemplate::PaymentReceipt => 'documents.payment_receipt',
            DocumentTemplate::AccreditedCertificate => 'documents.accredited_certificate',
            DocumentTemplate::FeedbackReport => 'documents.feedback_report',
            null => throw new InvalidArgumentException('Unknown document template.'),
        };
        $previousLocale = $this->translator->getLocale();
        $this->translator->setLocale($document->locale);

        try {
            return $this->views->make($view, [
                'data' => $document->data,
                'page' => DocumentPage::of($document),
                'assets' => $this->assets,
                'qrCodes' => $this->qrCodes,
            ])->render();
        } finally {
            $this->translator->setLocale($previousLocale);
        }
    }
}
