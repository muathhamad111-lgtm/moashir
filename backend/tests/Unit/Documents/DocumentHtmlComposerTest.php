<?php

declare(strict_types=1);

use App\Data\Documents\PdfDocument;
use App\Enums\Documents\DocumentTemplate;
use App\Services\Documents\DocumentHtmlComposer;
use App\Services\Documents\SampleDocuments;

/**
 * @return array<string, PdfDocument>
 */
function sampleDocuments(): array
{
    return app(SampleDocuments::class)->all();
}

it('renders every document as self-contained HTML that loads nothing from outside', function (string $name, string $locale, string $direction): void {
    $html = app(DocumentHtmlComposer::class)->compose(sampleDocuments()[$name]);

    expect($html)->toContain(sprintf('<html lang="%s" dir="%s">', $locale, $direction))
        ->and($html)->toContain("default-src 'none'; img-src data:; font-src data:")
        ->and($html)->not->toMatch('/\b(?:src|href)\s*=\s*"(?!data:)/i')
        ->and($html)->not->toMatch('/url\(\s*(?!["\']?data:)/i')
        ->and($html)->not->toContain('<script')
        ->and($html)->toContain('data:font/ttf;base64,')
        ->and($html)->toContain('data:font/woff2;base64,');
})->with([
    'certificate' => ['sample-certificate-gold.pdf', 'ar', 'rtl'],
    'participation certificate' => ['sample-certificate-participation.pdf', 'ar', 'rtl'],
    'Arabic feedback report' => ['sample-feedback-report-ar.pdf', 'ar', 'rtl'],
    'English feedback report' => ['sample-feedback-report-en.pdf', 'en', 'ltr'],
    'receipt' => ['sample-receipt.pdf', 'ar', 'rtl'],
]);

it('prints the percentage as number then sign in an isolated run and the issue year on the seal', function (): void {
    $html = app(DocumentHtmlComposer::class)->compose(sampleDocuments()['sample-certificate-gold.pdf']);

    expect($html)->toContain('dir="ltr">87.50%</bdi>')
        ->and($html)->toContain('<span class="certificate__seal-year" aria-hidden="true">'.now(config()->string('app.display_timezone'))->year.'</span>')
        ->and($html)->toContain('data:image/svg+xml;base64,')
        ->and($html)->toContain('المستوى الذهبي')
        ->and($html)->toContain('Gold level')
        ->and($html)->toContain('غير صالحة للتحقق')
        ->and($html)->toContain('Not valid for verification')
        ->and($html)->not->toContain('class="watermark"')
        ->and($html)->not->toContain('اختبار')
        ->and($html)->not->toContain('تجريبي');
});

it('prints a participation certificate without a level seal', function (): void {
    $html = app(DocumentHtmlComposer::class)->compose(sampleDocuments()['sample-certificate-participation.pdf']);

    expect($html)->toContain('شهادة مشاركة')
        ->and($html)->toContain('Certificate of Participation')
        ->and($html)->not->toContain('<span class="certificate__seal-year"');
});

it('escapes every value that comes from the data', function (): void {
    $sample = sampleDocuments()['sample-certificate-gold.pdf'];
    $hostile = '<script>alert(1)</script><img src="https://attacker.example/x.png">';
    $document = new PdfDocument(DocumentTemplate::AccreditedCertificate->value, [
        ...$sample->data,
        'entity_name' => ['ar' => $hostile, 'en' => $hostile],
        'request_number' => '"><iframe src="https://attacker.example">',
    ]);

    $html = app(DocumentHtmlComposer::class)->compose($document);

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('<img src="https://attacker.example')
        ->and($html)->not->toContain('<iframe')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->not->toContain('class="watermark"');
});

it('renders the English report in English and restores the application locale', function (): void {
    app()->setLocale('ar');

    $html = app(DocumentHtmlComposer::class)->compose(sampleDocuments()['sample-feedback-report-en.pdf']);

    expect($html)->toContain('Feedback report')
        ->and($html)->toContain('Results by main criterion')
        ->and($html)->toContain('Improvement points')
        ->and(app()->getLocale())->toBe('ar')
        ->and(trans('documents.feedback_report.title'))->toBe('التقرير التعقيبي');
});

it('refuses a template that is not a document view', function (): void {
    expect(fn (): string => app(DocumentHtmlComposer::class)->compose(new PdfDocument('../../welcome', [])))
        ->toThrow(InvalidArgumentException::class);
});
