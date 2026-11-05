<?php

declare(strict_types=1);

use App\Integrations\Documents\ChromiumPdfRenderer;
use App\Services\Documents\SampleDocuments;

it('renders the sample documents as tagged PDFs with embedded fonts using the real headless shell', function (): void {
    $binary = (string) getenv('PDF_TEST_CHROME_BINARY');

    if ($binary === '' || ! is_file($binary) || ! is_executable($binary)) {
        $this->markTestSkipped('Set PDF_TEST_CHROME_BINARY to a chrome-headless-shell binary to run the real renderer test.');
    }

    config()->set('moashir.documents.chrome_binary', $binary);
    $renderer = app(ChromiumPdfRenderer::class);

    foreach (app(SampleDocuments::class)->all() as $name => $document) {
        $pdf = $renderer->render($document);

        expect($pdf)->toStartWith('%PDF-')
            ->and($pdf)->toContain('/StructTreeRoot')
            ->and($pdf)->toContain('/FontFile2')
            ->and($pdf)->not->toContain('/URI')
            ->and(strlen($pdf))->toBeGreaterThan(20_000, $name);
    }
});
