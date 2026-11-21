<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Audit\AuditEntry;
use App\Data\Finance\TransferPromotionResult;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Certificates\CertificateStatus;
use App\Enums\Integrations\IntegrationMode;
use App\Exceptions\ApiException;
use App\Models\Certificates\Certificate;
use App\Models\Finance\Payment;
use App\Services\Audit\AuditRecorder;
use App\Services\Certificates\CertificateReplacement;
use App\Services\Finance\TestBankTransferCandidates;
use App\Support\RequestContext;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Operator decision 2026-11-19: bank transfers recorded as test become official. The payment turns
 * live and every current test certificate of a COMPLETED request is re-issued as an official
 * replacement. A certificate of a request that is not completed (reopened, under review) is left as
 * it is and recorded as skipped: the next certificate of that request comes out official because
 * the payment is live. Each payment is promoted in its own transaction; a failure rolls that payment
 * back, is reported, and the others continue.
 */
final readonly class PromoteTestBankTransfers
{
    private const string SKIPPED_NOT_COMPLETED = 'assessment_not_completed';

    private const string UNEXPECTED = 'unexpected_error';

    public function __construct(
        private TestBankTransferCandidates $candidates,
        private CertificateReplacement $replacement,
        private AuditRecorder $audit,
        private RequestContext $context,
    ) {}

    public function handle(string $reason): TransferPromotionResult
    {
        $this->context->recordReason($reason);
        $promoted = [];
        $failed = [];

        foreach ($this->candidates->query()->orderBy('id')->get(['id', 'reference']) as $candidate) {
            $reference = ScalarValue::string($candidate->reference);

            try {
                DB::transaction(fn () => $this->promote(ScalarValue::int($candidate->id), $reason));
                $promoted[] = $reference;
            } catch (Throwable $exception) {
                report($exception);
                $failed[$reference] = $exception instanceof ApiException ? $exception->error()->code : self::UNEXPECTED;
            }
        }

        return new TransferPromotionResult($promoted, $failed);
    }

    private function promote(int $paymentId, string $reason): void
    {
        $payment = $this->candidates->query()->whereKey($paymentId)->lockForUpdate()->firstOrFail();
        $payment->forceFill(['mode' => IntegrationMode::Live])->save();
        ['reissued' => $reissued, 'skipped' => $skipped] = $this->reissueTestCertificatesOf($payment, $reason);

        $this->audit->record(new AuditEntry(AuditEvent::BankTransferPromotedToLive, $payment, ['mode' => IntegrationMode::Test->value], [
            'mode' => IntegrationMode::Live->value,
            'reissued_certificates' => $reissued,
            'skipped_certificates' => $skipped,
        ]));
    }

    /**
     * @return array{reissued: list<string>, skipped: list<array{number: string, reason: string}>}
     */
    private function reissueTestCertificatesOf(Payment $payment, string $reason): array
    {
        $reissued = [];
        $skipped = [];
        $certificates = Certificate::query()
            ->where('is_test', true)
            ->whereIn('status', [CertificateStatus::Issued, CertificateStatus::Suspended])
            ->whereIn('assessment_id', DB::table('assessments')->where('payment_id', $payment->id)->select('id'))
            ->with('assessment')
            ->orderBy('id')
            ->get();

        foreach ($certificates as $certificate) {
            if ($certificate->assessment?->status !== AccreditedAssessmentStatus::Completed) {
                $skipped[] = ['number' => $certificate->number, 'reason' => self::SKIPPED_NOT_COMPLETED];

                continue;
            }

            $reissued[] = $this->replacement->replace($certificate, null, $reason)->number;
        }

        return ['reissued' => $reissued, 'skipped' => $skipped];
    }
}
