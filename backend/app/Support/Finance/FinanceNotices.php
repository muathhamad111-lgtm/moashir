<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Models\Finance\Payment;
use App\Support\ValueObjects\LocalizedText;

final class FinanceNotices
{
    public const string FOR_ENTITY = 'entity';

    public const string FOR_STAFF = 'staff';

    public static function forPayment(NotificationCode $code, string $audience, Payment $payment): EntityNotice
    {
        $key = 'notifications.payments.'.$code->value.'.'.$audience;
        $replacements = self::replacementsOf($payment);
        $link = $audience === self::FOR_ENTITY ? FinanceLinks::myPayment($payment->public_id) : FinanceLinks::transaction($payment->public_id);

        return new EntityNotice(
            $code,
            LocalizedText::fromTranslationWith($key.'.title', $replacements),
            LocalizedText::fromTranslationWith($key.'.body', $replacements),
            $link,
            true,
            true,
        );
    }

    public static function exportReady(string $exportPublicId, int $rowCount): EntityNotice
    {
        $key = 'notifications.payments.'.NotificationCode::FinanceExportReady->value;
        $replacements = ['count' => (string) $rowCount];

        return new EntityNotice(
            NotificationCode::FinanceExportReady,
            LocalizedText::fromTranslationWith($key.'.title', $replacements),
            LocalizedText::fromTranslationWith($key.'.body', $replacements),
            FinanceLinks::export($exportPublicId),
            false,
            true,
        );
    }

    /**
     * @return array<string, LocalizedText|string>
     */
    private static function replacementsOf(Payment $payment): array
    {
        return [
            'reference' => $payment->reference,
            'purpose' => LocalizedText::fromTranslation('finance.purposes.'.$payment->purpose->value),
            'amount' => LocalizedText::fromTranslationWith('finance.amount', [
                'amount' => $payment->total,
                'currency' => LocalizedText::fromTranslation('finance.currencies.'.$payment->currency),
            ]),
            'entity' => $payment->entity_name->toLocalizedText(),
        ];
    }
}
