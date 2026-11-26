<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Data\Mail\RequestedItem;
use App\Data\Mail\RequestedItems;
use App\Enums\Assessments\CompletionItemKind;
use App\Enums\Identity\Locale;
use App\Enums\Notifications\NotificationCode;
use App\Support\ValueObjects\LocalizedText;

/**
 * Fictional items for the preview of the completion e-mails (ACC-07, ADV-25), so the admin preview shows the items
 * list the real e-mail carries. Every other notification gets none.
 */
final class SampleRequestedItems
{
    private const array CODES = [NotificationCode::AccreditedCompletionRequested, NotificationCode::AdvancedReturnedForCompletion];

    /**
     * @var list<array{code: string, name: array{ar: string, en: string}, kind: CompletionItemKind, note: array{ar: string, en: string}|null}>
     */
    private const array ITEMS = [
        [
            'code' => '1.2',
            'name' => ['ar' => 'سياسة تعارض المصالح', 'en' => 'Conflict of interest policy'],
            'kind' => CompletionItemKind::Evidence,
            'note' => ['ar' => 'الملف المرفق غير معتمد من مجلس الإدارة. أرفق النسخة المعتمدة.', 'en' => 'The attached file is not approved by the board. Attach the approved version.'],
        ],
        [
            'code' => '2.1',
            'name' => ['ar' => 'القوائم المالية المدققة', 'en' => 'Audited financial statements'],
            'kind' => CompletionItemKind::Evidence,
            'note' => ['ar' => 'أرفق القوائم المالية المدققة لعام 2025.', 'en' => 'Attach the audited financial statements for 2025.'],
        ],
        [
            'code' => '3.4',
            'name' => ['ar' => 'كفاءة فريق العمل', 'en' => 'Team competence'],
            'kind' => CompletionItemKind::Cv,
            'note' => null,
        ],
    ];

    public static function for(NotificationCode $code, Locale $locale): RequestedItems
    {
        if (! in_array($code, self::CODES, true)) {
            return new RequestedItems([]);
        }

        return new RequestedItems(array_map(
            static fn (array $item): RequestedItem => new RequestedItem(
                $item['code'],
                new LocalizedText($item['name']['ar'], $item['name']['en']),
                $item['kind'],
                $item['note'][$locale->value] ?? null,
            ),
            self::ITEMS,
        ));
    }
}
