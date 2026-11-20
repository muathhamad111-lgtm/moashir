<?php

declare(strict_types=1);

use App\Data\Mail\DetailRow;
use App\Data\Notifications\NoticeText;
use App\Enums\Notifications\MailDetailTone;
use App\Support\Mail\NoticeDetails;
use App\Support\ValueObjects\LocalizedText;

function detailSummary(array $rows): array
{
    return array_map(static fn (DetailRow $row): array => [$row->label, $row->value, $row->tone], $rows);
}

it('builds the rows in a fixed order from the values the wording uses, in the recipient language', function (): void {
    $text = new NoticeText('notifications.assessments.accredited.ACC-07.entity', [
        'number' => 'ACC-2026-000142',
        'entity' => new LocalizedText('جمعية المثال الخيرية', 'Al-Mithal Charitable Society'),
        'deadline' => '2026-12-03 23:59',
        'outcome' => '',
    ]);

    expect(detailSummary(NoticeDetails::of($text, 'ar')))->toBe([
        ['آخر موعد (بتوقيت الرياض)', '2026-12-03 23:59', MailDetailTone::Warning],
        ['رقم الطلب', 'ACC-2026-000142', MailDetailTone::Reference],
    ])->and(detailSummary(NoticeDetails::of($text, 'en'))[1])->toBe(['Request number', 'ACC-2026-000142', MailDetailTone::Reference]);
});

it('shows a localised value in the recipient language and names the entity when the wording does', function (): void {
    $text = new NoticeText('notifications.assessments.accredited.ACC-03.staff', [
        'number' => 'ACC-2026-000142',
        'entity' => new LocalizedText('جمعية المثال الخيرية', 'Al-Mithal Charitable Society'),
    ]);

    expect(detailSummary(NoticeDetails::of($text, 'en'))[1])->toBe(['Entity', 'Al-Mithal Charitable Society', MailDetailTone::Plain]);
});

it('skips empty values and values the wording does not use', function (): void {
    $text = new NoticeText('notifications.assessments.accredited.ACC-09.entity', [
        'number' => 'ACC-2026-000142',
        'certificate' => '',
        'entity' => 'جمعية المثال الخيرية',
    ]);

    expect(detailSummary(NoticeDetails::of($text, 'ar')))->toBe([['رقم الطلب', 'ACC-2026-000142', MailDetailTone::Reference]]);
});
