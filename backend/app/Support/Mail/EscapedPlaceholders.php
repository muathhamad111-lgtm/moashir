<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Illuminate\Support\Str;

/**
 * Puts the values of a notice's `:placeholders` into HTML that is already rendered, each value escaped as literal
 * text. Values come from users (an entity name, a person's name), so they are inserted only after the
 * administrator's Markdown-lite is parsed and can never form bold text, a link or markup. The `:key`, `:KEY` and
 * `:Key` forms follow the translator's own replacement rules.
 */
final class EscapedPlaceholders
{
    /**
     * @param  array<string, string>  $values
     */
    public static function fill(string $html, array $values): string
    {
        $map = [];

        foreach ($values as $key => $value) {
            $map[':'.$key] = e($value);
            $map[':'.Str::upper($key)] = e(Str::upper($value));
            $map[':'.Str::ucfirst($key)] = e(Str::ucfirst($value));
        }

        return strtr($html, $map);
    }
}
