<?php

declare(strict_types=1);

/**
 * Operator decision (2026-11-16): the platform shows no test or trial wording to anyone.
 * Test modes keep working; they are worded «المعاينة» / "preview" and checks «التحقق» / "check".
 */
const ARABIC_TEST_WORDS = '/اختبار|ختبر|تجريبي|تجربة|وهمي/u';

const ENGLISH_TEST_WORDS = '/\b(test|tests|tested|testing|trial|demo|sandbox|mock)\b/i';

/**
 * @return list<string>
 */
function translatedLinesOf(string $locale): array
{
    $lines = [];

    foreach (glob(lang_path($locale.'/*.php')) ?: [] as $file) {
        $messages = require $file;

        if (! is_array($messages)) {
            continue;
        }

        array_walk_recursive(
            $messages,
            static function (mixed $value) use (&$lines, $file): void {
                if (is_string($value)) {
                    $lines[] = basename($file).': '.$value;
                }
            },
        );
    }

    return $lines;
}

it('keeps every Arabic message free of test or trial wording', function (): void {
    $lines = translatedLinesOf('ar');

    expect($lines)->not->toBeEmpty()
        ->and(preg_grep(ARABIC_TEST_WORDS, $lines))->toBe([]);
});

it('keeps every English message free of test or trial wording', function (): void {
    $lines = translatedLinesOf('en');

    expect($lines)->not->toBeEmpty()
        ->and(preg_grep(ENGLISH_TEST_WORDS, $lines))->toBe([]);
});
