<?php

declare(strict_types=1);

use App\Data\Notifications\NoticeText;

it('reads the summary line and button label of a template that has them, with its variables filled', function (): void {
    $text = new NoticeText('notifications.assessments.accredited.ACC-09.entity', ['certificate' => 'CRT-2026-000087', 'number' => 'ACC-2026-000142']);

    expect($text->summary()?->ar)->toBe('الشهادة CRT-2026-000087 جاهزة للتنزيل.')
        ->and($text->summary()?->en)->toBe('Certificate CRT-2026-000087 is ready to download.')
        ->and($text->action()?->ar)->toBe('عرض الشهادات')
        ->and($text->action()?->en)->toBe('View certificates');
});

it('has no summary line, button label or text message for a template without those parts', function (): void {
    $text = new NoticeText('notifications.assessments.accredited.ACC-09.staff', ['certificate' => 'CRT-2026-000087', 'number' => 'ACC-2026-000142', 'entity' => 'جمعية المثال']);

    expect($text->summary())->toBeNull()
        ->and($text->action())->toBeNull()
        ->and($text->sms())->toBeNull();
});

it('keeps the text message apart from the e-mail-only parts', function (): void {
    $text = new NoticeText('notifications.assessments.accredited.ACC-07.entity', ['number' => 'ACC-2026-000142', 'deadline' => '2026-12-03', 'outcome' => '']);

    expect($text->summary())->not->toBeNull()
        ->and($text->sms())->toBeNull();
});
