<?php

declare(strict_types=1);

use App\Data\Documents\PdfDocument;
use App\Enums\Documents\DocumentTemplate;
use App\Services\Documents\DocumentHtmlComposer;
use App\Services\Documents\SampleDocuments;

it('prints the sample gold certificate with the entity logo and the silver one without it', function (): void {
    $samples = app(SampleDocuments::class)->all();
    $gold = app(DocumentHtmlComposer::class)->compose($samples['sample-certificate-gold.pdf']);
    $silver = app(DocumentHtmlComposer::class)->compose($samples['sample-certificate-silver.pdf']);

    expect($gold)->toContain('<div class="certificate__entity-row">')
        ->and($gold)->toContain('<img class="certificate__entity-logo" src="data:image/png;base64,')
        ->and($gold)->toContain('alt="شعار جمعية الأمل الخيرية للتنمية الأسرية"')
        ->and($silver)->not->toContain('<img class="certificate__entity-logo"')
        ->and($silver)->toContain('المستوى الفضي');
});

it('never prints an entity logo that is not an inline image', function (string $logo): void {
    $sample = app(SampleDocuments::class)->all()['sample-certificate-gold.pdf'];
    $document = new PdfDocument(DocumentTemplate::AccreditedCertificate->value, [...$sample->data, 'entity_logo' => $logo]);

    expect(app(DocumentHtmlComposer::class)->compose($document))->not->toContain('<img class="certificate__entity-logo"');
})->with([
    'remote address' => ['https://attacker.example/logo.png'],
    'inline markup' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
]);
