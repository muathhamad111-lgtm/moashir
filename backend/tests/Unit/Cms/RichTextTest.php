<?php

declare(strict_types=1);

use App\Enums\Cms\LegalDocument;
use App\Rules\FreeOfHtmlMarkup;
use App\Services\Cms\LegalDocumentSource;
use App\Services\Cms\LegalPageSections;
use App\Support\RichText\RichTextParser;
use App\Support\RichText\RichTextSanitizer;
use App\Support\RichText\TitledText;

it('turns headings, paragraphs, lists and tables into typed blocks', function (): void {
    $text = implode("\n", [
        '## 1. نطاق السياسة',
        'تنطبق هذه السياسة على زوار المنصة',
        'وعلى الجهات المسجلة فيها.',
        '',
        '- الزوار؛',
        '- الجهات المسجلة.',
        '3. حق الوصول.',
        '4. حق التصحيح.',
        '',
        '### 1.1 مدد الاحتفاظ',
        '| فئة البيانات | مدة الاحتفاظ |',
        '|---|---|',
        '| رسائل «تواصل معنا» | 24 شهراً |',
        '| سجل التحقق | 365 يوماً | زائد |',
    ]);

    expect(new RichTextParser()->parse($text)->toArray())->toBe([
        ['type' => 'heading', 'level' => 2, 'text' => '1. نطاق السياسة'],
        ['type' => 'paragraph', 'text' => 'تنطبق هذه السياسة على زوار المنصة وعلى الجهات المسجلة فيها.'],
        ['type' => 'list', 'ordered' => false, 'start' => null, 'items' => ['الزوار؛', 'الجهات المسجلة.']],
        ['type' => 'list', 'ordered' => true, 'start' => 3, 'items' => ['حق الوصول.', 'حق التصحيح.']],
        ['type' => 'heading', 'level' => 3, 'text' => '1.1 مدد الاحتفاظ'],
        ['type' => 'table', 'header' => ['فئة البيانات', 'مدة الاحتفاظ', ''], 'rows' => [
            ['رسائل «تواصل معنا»', '24 شهراً', ''],
            ['سجل التحقق', '365 يوماً', 'زائد'],
        ]],
    ]);
});

it('keeps markup-like and unsupported syntax as literal text', function (): void {
    expect(new RichTextParser()->parse("#### Level four\n[[approval date]] and [link](javascript:alert(1))")->toArray())
        ->toBe([['type' => 'paragraph', 'text' => '#### Level four [[approval date]] and [link](javascript:alert(1))']]);
});

it('removes control and bidirectional override characters and normalizes line endings', function (): void {
    $dirty = "\u{FEFF}سطر أول\u{202E}\r\n\tسطر\u{0007} ثانٍ   \r\n\r\n\r\n\r\nسطر ثالث\u{200F}";

    expect(RichTextSanitizer::clean($dirty))->toBe("سطر أول\n سطر ثانٍ\n\nسطر ثالث\u{200F}")
        ->and(RichTextSanitizer::singleLine("عنوان\n  الصفحة\t"))->toBe('عنوان الصفحة');
});

it('detects HTML tags and comments but not comparisons', function (string $text, bool $hasMarkup): void {
    expect(FreeOfHtmlMarkup::contains($text))->toBe($hasMarkup);
})->with([
    ['<script>alert(1)</script>', true],
    ['نص <b>غامق</b>', true],
    ['<!-- comment -->', true],
    ['< img src=x onerror=alert(1)>', true],
    ['</p>', true],
    ['المدة < 30 يوماً و 5 > 3', false],
    ['[[رقم الإصدار]]', false],
]);

it('splits a leading level-one heading off as the title', function (): void {
    $titled = TitledText::fromMarkdown("# سياسة الخصوصية — منصة مؤشر\n\nمسودة للمراجعة القانونية\n\n## 1. مقدمة");

    expect($titled->title)->toBe('سياسة الخصوصية — منصة مؤشر')
        ->and($titled->body)->toBe("مسودة للمراجعة القانونية\n\n## 1. مقدمة")
        ->and(TitledText::fromMarkdown('نص بلا عنوان')->title)->toBeNull();
});

it('reads every legal draft with its title, the draft line and the placeholders kept', function (LegalDocument $document): void {
    $text = new LegalDocumentSource()->read($document);
    $parser = new RichTextParser;

    expect($text->title->ar)->toContain('منصة مؤشر')
        ->and($text->title->en)->toContain('Moashir')
        ->and($text->body->ar)->toStartWith('مسودة للمراجعة القانونية — آخر تحديث: [[تاريخ الاعتماد]]')
        ->and($text->body->en)->toStartWith('Draft for legal review — last updated: [[approval date]]')
        ->and(FreeOfHtmlMarkup::contains($text->body->ar.$text->body->en))->toBeFalse()
        ->and(array_column($parser->parse($text->body->ar)->toArray(), 'type'))->toBe(array_column($parser->parse($text->body->en)->toArray(), 'type'));
})->with(LegalDocument::cases());

it('keeps the privacy policy tables, the AI section and the values filled by the PM', function (): void {
    $body = new LegalDocumentSource()->read(LegalDocument::PrivacyPolicy)->body;
    $blocks = new RichTextParser()->parse($body->en)->toArray();
    $tables = array_values(array_filter($blocks, static fn (array $block): bool => $block['type'] === 'table'));

    expect($tables)->toHaveCount(2)
        ->and($tables[0]['header'])->toBe(['Data category', 'Retention period'])
        ->and($tables[0]['rows'])->toContain(['Encrypted backups', '30 days, then destroyed'])
        ->and($body->en)->toContain('## 17. AI assistant')
        ->and($body->en)->toContain('"Personal data request"')
        ->and($body->ar)->toContain('«طلب يتعلق بالبيانات الشخصية»')
        ->and($body->ar)->toContain('| النسخ الاحتياطية المشفّرة | 30 يوماً، ثم تُتلف |');
});

it('splits the accessibility statement into an intro and twelve numbered sections in both languages', function (): void {
    $sections = new LegalPageSections()->of(new LegalDocumentSource()->read(LegalDocument::AccessibilityStatement)->body);

    expect($sections)->toHaveCount(13)
        ->and($sections[0]->key)->toBe('intro')
        ->and($sections[0]->title)->toBeNull()
        ->and($sections[0]->description?->ar)->toBe('مسودة للمراجعة القانونية — آخر تحديث: [[تاريخ الاعتماد]]')
        ->and($sections[1]->key)->toBe('section-1')
        ->and($sections[1]->title?->toArray())->toBe(['ar' => '1. التزامنا', 'en' => '1. Our commitment'])
        ->and($sections[12]->key)->toBe('section-12');

    foreach ($sections as $section) {
        expect(mb_strlen($section->description->ar ?? ''))->toBeLessThanOrEqual(5000);
    }
});
