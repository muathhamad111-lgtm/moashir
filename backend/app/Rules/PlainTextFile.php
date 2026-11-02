<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class PlainTextFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $contents = $value instanceof UploadedFile ? (string) $value->get() : '';

        if ($contents === '' || preg_match('/^[\x20-\x7E\r\n\t]+$/', $contents) !== 1) {
            $fail('validation.custom.apple_pay_file.content')->translate();
        }
    }
}
