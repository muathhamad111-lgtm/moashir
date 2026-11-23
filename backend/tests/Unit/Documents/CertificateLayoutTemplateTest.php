<?php

declare(strict_types=1);

use App\Data\Documents\PdfDocument;
use App\Services\Documents\DocumentHtmlComposer;
use App\Services\Documents\SampleDocuments;

// Operator 2026-11-22 (sample CRT-2026-000003): the entity name alone, three aligned fact rows, no visible
// verification code; the QR code still carries the verification link. Same layout on every certificate kind.

function certificateHtml(string $sample, array $extra = []): string
{
    $document = app(SampleDocuments::class)->all()[$sample];

    return app(DocumentHtmlComposer::class)->compose(new PdfDocument($document->template, [...$document->data, ...$extra]));
}

/**
 * @return list<string>
 */
function certificateSamples(): array
{
    return ['sample-certificate-gold.pdf', 'sample-certificate-participation.pdf', 'sample-award-winner.pdf', 'sample-award-qualified.pdf', 'sample-award-participation.pdf'];
}

it('prints no entity logo, even when the data carries one', function (string $sample): void {
    $html = certificateHtml($sample, ['entity_logo' => 'data:image/png;base64,iVBORw0KGgo=']);

    expect($html)->not->toContain('certificate__entity-logo')
        ->and($html)->not->toContain('data:image/png;base64,iVBORw0KGgo=')
        ->and($html)->toContain('<p class="certificate__entity">');
})->with(certificateSamples());

it('prints the number, the request and the issue date as three rows with the labels at both edges', function (string $sample): void {
    $html = certificateHtml($sample);

    expect(substr_count($html, '<th scope="row" class="certificate__facts-ar">'))->toBe(3)
        ->and(substr_count($html, '<th class="certificate__facts-en" lang="en" dir="ltr">'))->toBe(3);

    foreach ([['رقم الشهادة', 'Certificate number'], ['رقم الطلب', 'Request number'], ['تاريخ الإصدار', 'Issue date']] as [$ar, $en]) {
        expect($html)->toMatch('/<th scope="row" class="certificate__facts-ar">'.$ar.'<\/th>\s*<td[^>]*>.+?<\/td>\s*<th class="certificate__facts-en" lang="en" dir="ltr">'.$en.'<\/th>/su');
    }
})->with(certificateSamples());

it('keeps the QR code and its caption but shows no verification code', function (string $sample): void {
    $html = certificateHtml($sample);

    expect($html)->toContain('<figure class="certificate__qr">')
        ->and($html)->toMatch('/<figure class="certificate__qr">\s*<img src="data:image\//')
        ->and($html)->toContain('امسح الرمز للتحقق من صحة الشهادة')
        ->and($html)->not->toContain('رمز التحقق')
        ->and($html)->not->toContain('Verification code')
        ->and($html)->toContain('ويمكن التحقق من صحتها بمسح الرمز.')
        ->and($html)->toContain('Verify it by scanning the code.')
        ->and($html)->not->toContain('صفحة التحقق');
})->with(certificateSamples());
