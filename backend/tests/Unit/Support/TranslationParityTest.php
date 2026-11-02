<?php

declare(strict_types=1);

const LANG_DIRECTORY = __DIR__.'/../../../lang';

/**
 * @param  array<array-key, mixed>  $messages
 * @return list<string>
 */
function translationKeys(array $messages, string $prefix = ''): array
{
    $keys = [];

    foreach ($messages as $key => $value) {
        $path = $prefix.$key;
        $keys = is_array($value) ? [...$keys, ...translationKeys($value, $path.'.')] : [...$keys, $path];
    }

    return $keys;
}

it('ships every translation key in both Arabic and English', function (string $file): void {
    $english = translationKeys(require lang_path('en/'.$file));
    $arabic = translationKeys(require lang_path('ar/'.$file));

    expect(array_values(array_diff($english, $arabic)))->toBe([])
        ->and(array_values(array_diff($arabic, $english)))->toBe([]);
})->with(array_map(basename(...), glob(LANG_DIRECTORY.'/en/*.php') ?: []));

it('keeps Eastern Arabic digits out of every translation', function (string $file): void {
    $contents = (string) file_get_contents(lang_path($file));

    expect(preg_match('/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u', $contents))->toBe(0);
})->with(array_map(static fn (string $path): string => basename(dirname($path)).'/'.basename($path), glob(LANG_DIRECTORY.'/*/*.php') ?: []));
