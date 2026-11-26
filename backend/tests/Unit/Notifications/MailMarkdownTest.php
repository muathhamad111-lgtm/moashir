<?php

declare(strict_types=1);

use App\Support\Mail\MailMarkdown;
use Illuminate\Support\HtmlString;

/**
 * @param  array<string, string>  $values
 * @return list<string>
 */
function markdownLines(string $source, array $values = []): array
{
    return array_map(static fn (HtmlString $line): string => $line->toHtml(), MailMarkdown::toLines($source, $values));
}

it('turns paragraphs and list lines into e-mail lines with bold and https links', function (): void {
    expect(markdownLines("مرحبًا **سارة**\n\n- راجع [الدليل](https://example.sa/guide)\n- ثم أرسل"))->toBe([
        'مرحبًا <strong>سارة</strong>',
        '• راجع <a href="https://example.sa/guide">الدليل</a>',
        '• ثم أرسل',
    ]);
});

it('escapes everything that is not Markdown-lite and never links a non-https address', function (string $source, string $line): void {
    expect(markdownLines($source))->toBe([$line]);
})->with([
    'markup' => ['<img src=x onerror=alert(1)>', '&lt;img src=x onerror=alert(1)&gt;'],
    'plain http' => ['[here](http://phish.example)', '[here](http://phish.example)'],
    'script link' => ['[here](javascript:alert)', '[here](javascript:alert)'],
    'quote in a link' => ['[x](https://a.sa/"onmouseover=1)', '<a href="https://a.sa/&quot;onmouseover=1">x</a>'],
    'unclosed bold' => ['**bold', '**bold'],
]);

it('reads a rendered line as plain text with links spelled out', function (): void {
    expect(MailMarkdown::toText('راجع <a href="https://example.sa">الدليل</a> و<strong>أرسل</strong>'))->toBe('راجع الدليل (https://example.sa) وأرسل')
        ->and(MailMarkdown::toText('A &amp; B'))->toBe('A &amp; B');
});

it('fills a placeholder value in as literal text after parsing, so it never becomes a link, bold text or markup', function (string $value, string $line): void {
    expect(markdownLines('سجّلت :entity في المنصة', ['entity' => $value]))->toBe([$line]);
})->with([
    'link' => ['[حدّث بياناتك](https://evil.example/login)', 'سجّلت [حدّث بياناتك](https://evil.example/login) في المنصة'],
    'bold' => ['**عاجل**', 'سجّلت **عاجل** في المنصة'],
    'script' => ['<script>alert(1)</script>', 'سجّلت &lt;script&gt;alert(1)&lt;/script&gt; في المنصة'],
    'placeholder in a value' => [':reference', 'سجّلت :reference في المنصة'],
]);

it('keeps the administrator\'s own bold text and link around a filled value, in every placeholder form', function (): void {
    expect(markdownLines("**مرحبًا :name**\n- راجع [دليل :entity](https://example.sa/:entity)\n:NAME و:Name", ['name' => 'sara <b>', 'entity' => '**x**']))->toBe([
        '<strong>مرحبًا sara &lt;b&gt;</strong>',
        '• راجع <a href="https://example.sa/:entity">دليل **x**</a>',
        'SARA &lt;B&gt; وSara &lt;b&gt;',
    ]);
});
