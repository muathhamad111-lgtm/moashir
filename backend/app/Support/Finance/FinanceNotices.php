<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Data\Entities\EntityNotice;
use App\Data\Notifications\NoticeText;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\BankAccountChange;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Notifications\NotificationCode;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use App\Services\Paths\PathIdentityReader;
use App\Support\ValueObjects\LocalizedText;

final class FinanceNotices
{
    public const string FOR_ENTITY = 'entity';

    public const string FOR_STAFF = 'staff';

    public const string FOR_STAFF_TRANSFER = 'staff_transfer';

    public static function forPayment(NotificationCode $code, string $audience, Payment $payment): EntityNotice
    {
        $link = $audience === self::FOR_ENTITY ? FinanceLinks::myPayment($payment->public_id) : FinanceLinks::transaction($payment->public_id);

        return self::build($code, $audience, $payment, $link);
    }

    public static function forStaffWithLink(NotificationCode $code, Payment $payment, ?string $link): EntityNotice
    {
        return self::build($code, self::FOR_STAFF, $payment, $link);
    }

    public static function transferToReview(Payment $payment): EntityNotice
    {
        return self::build(NotificationCode::BankTransferSubmitted, self::FOR_STAFF, $payment, FinanceLinks::bankTransfer($payment->public_id));
    }

    public static function transferRejected(Payment $payment): EntityNotice
    {
        $notice = self::forPayment(NotificationCode::BankTransferRejected, self::FOR_ENTITY, $payment);
        $note = $payment->bankTransfer?->review_note;

        if ($note === null) {
            return $notice;
        }

        $noteLine = LocalizedText::fromTranslationWith('notifications.payments.'.NotificationCode::BankTransferRejected->value.'.entity.note', ['note' => $note]);
        $body = new LocalizedText($notice->body->ar.' '.$noteLine->ar, $notice->body->en.' '.$noteLine->en);

        return new EntityNotice($notice->code, $notice->title, $body, $notice->link, $notice->viaMail, $notice->viaInApp, $notice->path, $notice->text);
    }

    public static function bankAccountChanged(BankAccount $account, BankAccountChange $change): EntityNotice
    {
        $key = 'notifications.payments.'.NotificationCode::BankAccountsChanged->value;
        $replacements = [
            'bank' => $account->bank_name,
            'ending' => $account->ibanLastFour(),
            'change' => LocalizedText::fromTranslation('finance.bank_account_changes.'.$change->value),
        ];

        return EntityNotice::fromText(NotificationCode::BankAccountsChanged, new NoticeText($key, $replacements), FinanceLinks::paymentSettings());
    }

    private static function build(NotificationCode $code, string $audience, Payment $payment, ?string $link): EntityNotice
    {
        $key = 'notifications.payments.'.$code->value.'.'.$audience;
        $replacements = self::replacementsOf($payment);

        return EntityNotice::fromText($code, new NoticeText($key, $replacements), $link, $payment->purpose === PaymentPurpose::Accredited ? AssessmentPath::Accredited : null);
    }

    public static function exportReady(string $exportPublicId, int $rowCount): EntityNotice
    {
        $key = 'notifications.payments.'.NotificationCode::FinanceExportReady->value;
        $replacements = ['transactions' => LocalizedText::fromChoice('quantities.transactions', $rowCount)];

        return EntityNotice::fromText(NotificationCode::FinanceExportReady, new NoticeText($key, $replacements), FinanceLinks::export($exportPublicId))->inAppOnly();
    }

    /**
     * @return array<string, LocalizedText|string>
     */
    private static function replacementsOf(Payment $payment): array
    {
        return [
            'reference' => $payment->reference,
            'purpose' => app(PathIdentityReader::class)->purposeLabelOf($payment->purpose),
            'amount' => LocalizedText::fromTranslationWith('finance.amount', [
                'amount' => $payment->total,
                'currency' => LocalizedText::fromTranslation('finance.currencies.'.$payment->currency),
            ]),
            'entity' => $payment->entity_name->toLocalizedText(),
            'reason' => self::rejectionReasonOf($payment),
        ];
    }

    private static function rejectionReasonOf(Payment $payment): LocalizedText|string
    {
        $reason = $payment->relationLoaded('bankTransfer') ? $payment->bankTransfer?->rejection_reason : null;

        return $reason === null ? '' : LocalizedText::fromTranslation('finance.transfer_rejection_reasons.'.$reason->value);
    }
}
