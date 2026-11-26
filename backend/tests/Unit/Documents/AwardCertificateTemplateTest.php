<?php

declare(strict_types=1);

use App\Services\Documents\DocumentHtmlComposer;
use App\Services\Documents\SampleDocuments;

it('renders each award certificate in Arabic with its English line and no score', function (string $sample, string $arabicTitle, string $englishTitle, string $statement): void {
    $html = app(DocumentHtmlComposer::class)->compose(app(SampleDocuments::class)->all()[$sample]);

    expect($html)->toContain('lang="ar"')
        ->toContain('dir="rtl"')
        ->toContain($arabicTitle)
        ->toContain($englishTitle)
        ->toContain($statement)
        ->toContain('جمعية الأمل الخيرية')
        ->not->toContain('%</bdi>')
        ->not->toContain('certificate__percent"')
        ->not->toContain('certificate__seal"');
})->with([
    'winner' => ['sample-award-winner.pdf', 'شهادة الفوز بالجائزة', 'Award Winner Certificate', 'won second place nationally and first place in Riyadh Region'],
    'qualified' => ['sample-award-qualified.pdf', 'شهادة التأهل في الجائزة', 'Award Qualification Certificate', 'لتقييم لجنة المقيّمين عن منطقة الرياض'],
    'participation' => ['sample-award-participation.pdf', 'شهادة المشاركة في الجائزة', 'Award Participation Certificate', 'took part in Moashir Institutional Excellence Award'],
]);
