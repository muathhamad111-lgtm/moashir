<?php

declare(strict_types=1);

use App\Support\Search\SearchTextNormalizer;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Support\Facades\DB;

dataset('normalization samples', [
    'hamza above alef' => ['أَحْمَد', 'احمد'],
    'hamza below alef' => ['إسلام', 'اسلام'],
    'madda and taa marbuta' => ['آمنة', 'امنه'],
    'alef maqsura' => ['مستشفى', 'مستشفي'],
    'waw with hamza' => ['مؤسسة', 'موسسه'],
    'yaa with hamza' => ['هيئة', 'هييه'],
    'wasla' => ['ٱلرياض', 'الرياض'],
    'tatweel' => ['الــريـاض', 'الرياض'],
    'shadda and superscript alef' => ['المقيّم الرحمٰن', 'المقيم الرحمن'],
    'eastern arabic digits' => ['دورة ٢٠٢٦', 'دوره 2026'],
    'persian digits' => ['۱۴۴۷', '1447'],
    'latin case and spacing' => ["  Nora \t  ALOTAIBI ", 'nora alotaibi'],
]);

it('normalizes Arabic and Latin text for searching', function (string $text, string $expected): void {
    expect(SearchTextNormalizer::normalize($text))->toBe($expected);
})->with('normalization samples');

it('normalizes exactly like the database function', function (string $text, string $expected): void {
    $database = DB::selectOne('select '.SearchTextNormalizer::DATABASE_FUNCTION.'(?) as value', [$text]);

    expect($database?->value)->toBe($expected)->toBe(SearchTextNormalizer::normalize($text));
})->with('normalization samples');

it('refuses a term with nothing searchable', function (string $text): void {
    expect(SearchTerm::tryFrom($text))->toBeNull()
        ->and(fn (): SearchTerm => new SearchTerm($text))->toThrow(InvalidArgumentException::class);
})->with([
    'spaces' => ['   '],
    'tatweel and diacritics' => ['ـَـُ'],
]);

it('counts searchable characters without spaces', function (): void {
    expect(new SearchTerm(' a  b ')->length())->toBe(2)
        ->and(new SearchTerm('أَحْمَد')->length())->toBe(4);
});

it('escapes LIKE wildcards in the contains pattern', function (): void {
    expect(new SearchTerm('50%_off\\')->containsPattern())->toBe('%50\\%\\_off\\\\%');
});
