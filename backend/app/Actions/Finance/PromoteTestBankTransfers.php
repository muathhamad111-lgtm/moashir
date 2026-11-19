<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Certificates\CertificateStatus;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Certificates\Certificate;
use App\Models\Finance\Payment;
use App\Services\Audit\AuditRecorder;
use App\Services\Certificates\CertificateReplacement;
use App\Services\Finance\TestBankTransferCandidates;
use App\Support\RequestContext;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\DB;

/**
 * Operator decision 2026-11-19: bank transfers recorded as test become official. The payment turns
 * live and every current test certificate of its request is re-issued as an official replacement.
 * Receipts and certificates are append-only, so the old test receipt keeps «دون رقم» and the old
 * certificate is superseded, never edited.
 */
final readonly class PromoteTestBankTransfers
{
    private const array REPLACEABLE = [CertificateStatus::Issued, CertificateStatus::Suspended];

    public function __construct(
        private TestBankTransferCandidates $candidates,
        private CertificateReplacement $replacement,
        private AuditRecorder $audit,
        private RequestContext $context,
    ) {}

    /**
     * @return list<string> the references of the promoted payments
     */
    public function handle(string $reason): array
    {
        $this->context->recordReason($reason);
        $promoted = [];

        foreach ($this->candidates->query()->orderBy('id')->pluck('id') as $paymentId) {
            $reference = DB::transaction(fn (): ?string => $this->promote(ScalarValue::int($paymentId), $reason));

            if ($reference !== null) {
                $promoted[] = $reference;
            }
        }

        return $promoted;
    }

    private function promote(int $paymentId, string $reason): ?string
    {
        $payment = $this->candidates->query()->whereKey($paymentId)->lockForUpdate()->first();

        if (! $payment instanceof Payment) {
            return null;
        }

        $payment->forceFill(['mode' => IntegrationMode::Live])->save();
        $replacements = $this->reissueTestCertificatesOf($payment, $reason);

        $this->audit->record(new AuditEntry(AuditEvent::BankTransferPromotedToLive, $payment, ['mode' => IntegrationMode::Test->value], [
            'mode' => IntegrationMode::Live->value,
            'reissued_certificates' => $replacements,
        ]));

        return $payment->reference;
    }

    /**
     * @return list<string> the numbers of the official replacements
     */
    private function reissueTestCertificatesOf(Payment $payment, string $reason): array
    {
        $replacements = [];
        $certificates = Certificate::query()
            ->where('is_test', true)
            ->whereIn('status', self::REPLACEABLE)
            ->whereIn('assessment_id', DB::table('assessments')->where('payment_id', $payment->id)->select('id'))
            ->get();

        foreach ($certificates as $certificate) {
            $replacements[] = $this->replacement->replace($certificate, null, $reason)->number;
        }

        return $replacements;
    }
}
