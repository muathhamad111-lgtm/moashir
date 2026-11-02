<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final readonly class OpenMissingAccreditedRequests
{
    private const int CHUNK = 100;

    public function __construct(private OpenPaidAccreditedRequest $open, private Repository $config) {}

    public function handle(): int
    {
        $opened = 0;
        $paidBefore = Date::now()->subMinutes($this->config->integer('moashir.assessment.open_paid_requests_after_minutes'));

        Payment::query()
            ->select('id')
            ->where('purpose', PaymentPurpose::Accredited)
            ->where('status', PaymentStatus::Paid)
            ->where('paid_at', '<', $paidBefore)
            ->whereNotIn('id', Assessment::query()->ofPath(AssessmentPath::Accredited)->whereNotNull('payment_id')->select('payment_id'))
            ->whereNotIn('entity_id', Assessment::query()->ofPath(AssessmentPath::Accredited)->open()->select('entity_id'))
            ->chunkById(self::CHUNK, function (Collection $payments) use (&$opened): void {
                foreach ($payments as $payment) {
                    $opened += $this->open->handle($payment->id) instanceof Assessment ? 1 : 0;
                }
            });

        return $opened;
    }
}
