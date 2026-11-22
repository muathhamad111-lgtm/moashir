<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Illuminate\Support\HtmlString;

/**
 * Markdown-lite for an e-mail body an administrator wrote: **bold**, [label](https://…) and "- " list lines.
 * Everything else is escaped, so no HTML an administrator types can reach an inbox. Each paragraph or list item
 * becomes one e-mail line; the text part reads the same lines with the tags taken out and links spelled out.
 * The template is parsed with its `:placeholders` still in place and the values are filled in afterwards as
 * escaped text (never inside a link address), so a value such as an entity name cannot add a link or bold text.
 */
final class MailMarkdown
{
    private const string INLINE = '/(\*\*[^*\n]+\*\*|\[[^\]\n]+\]\(https:\/\/[^)\s]+\))/u';

    private const string BOLD = '/^\*\*([^*\n]+)\*\*$/u';

    private const string LINK = '/^\[([^\]\n]+)\]\((https:\/\/[^)\s]+)\)$/u';

    private const string ANCHOR = '/<a href="([^"]*)">(.*?)<\/a>/u';

    private const string LIST_MARK = '- ';

    private const string BULLET = '• ';

    /**
     * @param  array<string, string>  $values
     * @return list<HtmlString>
     */
    public static function toLines(string $template, array $values = []): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', $template) ?: [] as $raw) {
            $line = trim($raw);
            if ($line === '') {
                continue;
            }

            $isItem = str_starts_with($line, self::LIST_MARK);
            $html = self::inline($isItem ? substr($line, strlen(self::LIST_MARK)) : $line, $values);
            $lines[] = new HtmlString(($isItem ? self::BULLET : '').$html);
        }

        return $lines;
    }

    /**
     * A rendered e-mail line as plain text: links become «label (url)» and every other tag goes.
     */
    public static function toText(string $html): string
    {
        return strip_tags((string) preg_replace(self::ANCHOR, '$2 ($1)', $html));
    }

    /**
     * @param  array<string, string>  $values
     */
    private static function inline(string $text, array $values): string
    {
        $parts = preg_split(self::INLINE, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_map(static fn (string $part): string => self::token($part, $values), $parts));
    }

    /**
     * @param  array<string, string>  $values
     */
    private static function token(string $part, array $values): string
    {
        if (preg_match(self::BOLD, $part, $bold) === 1) {
            return '<strong>'.EscapedPlaceholders::fill(e($bold[1]), $values).'</strong>';
        }

        if (preg_match(self::LINK, $part, $link) === 1) {
            return '<a href="'.e($link[2]).'">'.EscapedPlaceholders::fill(e($link[1]), $values).'</a>';
        }

        return EscapedPlaceholders::fill(e($part), $values);
    }
}
