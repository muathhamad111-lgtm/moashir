<?php

declare(strict_types=1);

use App\Rules\SafeLinkUrl;
use App\Support\ValueObjects\KpiNumber;
use App\Support\ValueObjects\LocalizedContent;

it('fills a missing language from the other one and keeps the stored values unchanged', function (): void {
    $content = new LocalizedContent('منصة مؤشر', '  ');

    expect($content->toArray())->toBe(['ar' => 'منصة مؤشر', 'en' => ''])
        ->and($content->withFallback())->toBe(['ar' => 'منصة مؤشر', 'en' => 'منصة مؤشر'])
        ->and($content->in('en'))->toBe('منصة مؤشر')
        ->and(LocalizedContent::tryFromArray(['ar' => '', 'en' => null]))->toBeNull()
        ->and(LocalizedContent::tryFromArray(['en' => 'Only English'])?->withFallback())->toBe(['ar' => 'Only English', 'en' => 'Only English']);
});

it('refuses localized content without any language', function (): void {
    new LocalizedContent('', '');
})->throws(InvalidArgumentException::class);

it('normalizes KPI numbers without floating point', function (string $input, string $expected): void {
    expect(new KpiNumber($input)->value)->toBe($expected);
})->with([
    ['486.0000', '486'],
    ['0071.50', '71.5'],
    ['-0.000', '0'],
    ['-12.25', '-12.25'],
    ['99999999999999.9999', '99999999999999.9999'],
]);

it('rejects KPI numbers that do not fit numeric(18,4)', function (mixed $input): void {
    expect(KpiNumber::tryFrom($input))->toBeNull();
})->with(['1.23456', '123456789012345', 'abc', '', 1.5, null]);

it('accepts site paths, anchors, mailto and https links only', function (string $link, bool $safe): void {
    expect(SafeLinkUrl::isSafe($link))->toBe($safe);
})->with([
    ['/kpis', true],
    ['/assessment/self?step=1', true],
    ['#paths', true],
    ['mailto:info@moashir.example.sa', true],
    ['https://example.sa/page', true],
    ['//evil.example.com', false],
    ['http://example.sa', false],
    ['javascript:alert(1)', false],
    ['data:text/html;base64,AAAA', false],
    ['https://example.sa/"onmouseover', false],
]);
