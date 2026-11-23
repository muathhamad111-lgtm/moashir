<?php

declare(strict_types=1);

use App\Data\Documents\PdfDocument;
use App\Services\Documents\DocumentHtmlComposer;
use App\Services\Documents\SampleDocuments;

// Operator 2026-11-22 (relayed by the PM, «اعتمدها كما هي»): verbal nouns only, so one sentence fits a جمعية, a مؤسسة
// or a وقف. Each certificate reads «تشهد منصة مؤشر ب…» + the entity name + the rest of the sentence.

function wordingOf(string $sample): string
{
    $html = app(DocumentHtmlComposer::class)->compose(app(SampleDocuments::class)->all()[$sample]);

    return (string) preg_replace('/\s+/u', ' ', strip_tags($html));
}

it('states each certificate in one gender-neutral Arabic sentence', function (string $sample, string $lead, string $rest): void {
    $text = wordingOf($sample);
    $year = now(config('app.display_timezone'))->year;

    expect($text)->toContain($lead.' جمعية الأمل الخيرية')
        ->and($text)->toContain(str_replace(':year', (string) $year, $rest));
})->with([
    'accredited' => ['sample-certificate-gold.pdf', 'تشهد منصة مؤشر بإتمام', 'التقييم المعتمد وفق نموذج التميز في العمل الخيري بنسبة 87.50%، وتحقيق المستوى الذهبي.'],
    'accredited participation' => ['sample-certificate-participation.pdf', 'تشهد منصة مؤشر بإتمام', 'التقييم المعتمد وفق نموذج التميز في العمل الخيري بنسبة 54.25%.'],
    'national winner' => ['sample-award-winner.pdf', 'تشهد منصة مؤشر بحصول', 'على المركز الثاني على مستوى المملكة والمركز الأول في منطقة الرياض ضمن دورة جائزة مؤشر للتميز المؤسسي :year.'],
    'qualified' => ['sample-award-qualified.pdf', 'تشهد منصة مؤشر بتأهّل', 'لتقييم لجنة المقيّمين عن منطقة الرياض ضمن دورة جائزة مؤشر للتميز المؤسسي :year.'],
    'participation' => ['sample-award-participation.pdf', 'تشهد منصة مؤشر بمشاركة', 'في دورة جائزة مؤشر للتميز المؤسسي :year وإتمام مرحلة التقييم الأولي.'],
]);

it('uses no gendered verb or agreement on any certificate', function (string $sample): void {
    expect(wordingOf($sample))->not->toContain('بأن')
        ->not->toContain('قد أتمّت')
        ->not->toContain('حصلت')
        ->not->toContain('محققةً')
        ->not->toContain('قد فازت')
        ->not->toContain('قد تأهّلت')
        ->not->toContain('قد شاركت');
})->with(['sample-certificate-gold.pdf', 'sample-certificate-participation.pdf', 'sample-award-winner.pdf', 'sample-award-qualified.pdf', 'sample-award-participation.pdf']);

it('words the regional winner without a national rank', function (): void {
    $sample = app(SampleDocuments::class)->all()['sample-award-winner.pdf'];
    $document = new PdfDocument($sample->template, [...$sample->data, 'national_rank' => null]);
    $text = (string) preg_replace('/\s+/u', ' ', strip_tags(app(DocumentHtmlComposer::class)->compose($document)));

    expect($text)->toContain('تشهد منصة مؤشر بحصول جمعية الأمل الخيرية')
        ->and($text)->toContain('على المركز الأول في منطقة الرياض ضمن دورة جائزة مؤشر للتميز المؤسسي')
        ->and($text)->not->toContain('على مستوى المملكة')
        ->and($text)->toContain('won first place in Riyadh Region in Moashir Institutional Excellence Award');
});
