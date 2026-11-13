<?php

declare(strict_types=1);

use App\Contracts\Documents\HtmlPdfPrinter;
use App\Contracts\Documents\PdfRenderer;
use App\Data\Documents\PdfDocument;
use App\Exceptions\Documents\DocumentRenderingFailed;
use App\Integrations\Documents\ChromiumPdfRenderer;
use App\Integrations\Documents\HeadlessChromePrinter;
use App\Integrations\Documents\UnavailablePdfRenderer;
use App\Services\Documents\SampleDocuments;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

function recordingPrinter(): HtmlPdfPrinter
{
    $printer = new class implements HtmlPdfPrinter
    {
        /**
         * @var list<string>
         */
        public array $printed = [];

        public function print(string $html): string
        {
            $this->printed[] = $html;

            return "%PDF-1.7\n%recorded\n%%EOF\n";
        }
    };

    app()->instance(HtmlPdfPrinter::class, $printer);

    return $printer;
}

function sampleCertificate(): PdfDocument
{
    return app(SampleDocuments::class)->all()['sample-certificate-gold.pdf'];
}

it('composes the document and prints it through the browser printer', function (): void {
    $printer = recordingPrinter();

    $pdf = app(ChromiumPdfRenderer::class)->render(sampleCertificate());

    expect($pdf)->toStartWith('%PDF-')
        ->and($printer->printed)->toHaveCount(1)
        ->and($printer->printed[0])->toContain('شهادة التقييم المعتمد');
});

it('answers 503 renderer_busy when another render holds the only slot', function (): void {
    $printer = recordingPrinter();
    config()->set('moashir.documents.render_queue_wait_seconds', 0);
    $slot = Cache::lock(config()->string('moashir.documents.render_slot_key').'1', 60);
    $slot->get();

    try {
        app(ChromiumPdfRenderer::class)->render(sampleCertificate());
        $this->fail('The render should have been refused.');
    } catch (DocumentRenderingFailed $exception) {
        expect($exception->error()->code)->toBe('document.renderer_busy')
            ->and($exception->error()->status)->toBe(503)
            ->and($printer->printed)->toBe([]);
    } finally {
        $slot->release();
    }
});

it('releases the slot after a render so the next document can print', function (): void {
    $printer = recordingPrinter();
    config()->set('moashir.documents.render_queue_wait_seconds', 0);

    app(ChromiumPdfRenderer::class)->render(sampleCertificate());
    app(ChromiumPdfRenderer::class)->render(sampleCertificate());

    expect($printer->printed)->toHaveCount(2);
});

it('maps a browser that cannot start to 503 render_failed, reports it and removes the temporary profile', function (): void {
    config()->set('moashir.documents.chrome_binary', base_path('tests/Support/missing-chrome-headless-shell'));
    $before = glob(sys_get_temp_dir().'/moashir-pdf-*') ?: [];

    try {
        app(HeadlessChromePrinter::class)->print('<html><body>اختبار</body></html>');
        $this->fail('The print should have failed.');
    } catch (DocumentRenderingFailed $exception) {
        expect($exception->error()->code)->toBe('document.render_failed');
    }

    expect(glob(sys_get_temp_dir().'/moashir-pdf-*') ?: [])->toBe($before);
});

it('binds the Chromium renderer only when the configured binary is executable', function (): void {
    Log::spy();
    config()->set('moashir.documents.pdf_renderer', 'chromium');
    config()->set('moashir.documents.chrome_binary', PHP_BINARY);
    app()->forgetInstance(PdfRenderer::class);

    expect(app(PdfRenderer::class))->toBeInstanceOf(ChromiumPdfRenderer::class);

    config()->set('moashir.documents.chrome_binary', base_path('tests/Support/missing-chrome-headless-shell'));
    app()->forgetInstance(PdfRenderer::class);

    expect(app(PdfRenderer::class))->toBeInstanceOf(UnavailablePdfRenderer::class);
    Log::shouldHaveReceived('critical')->with('documents.chrome_binary_unusable', ['setting' => 'moashir.documents.chrome_binary'])->once();
});
