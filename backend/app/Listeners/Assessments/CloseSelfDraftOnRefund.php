<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Actions\Assessments\CloseRefundedSelfDraft;
use App\Enums\Finance\PaymentPurpose;
use App\Events\Finance\PaymentRefunded;
use Illuminate\Contracts\Queue\ShouldQueue;

final class CloseSelfDraftOnRefund implements ShouldQueue
{
    public int $tries = 5;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(private readonly CloseRefundedSelfDraft $close) {}

    public function shouldQueue(PaymentRefunded $event): bool
    {
        return $event->payment->purpose === PaymentPurpose::SelfAssessment;
    }

    public function handle(PaymentRefunded $event): void
    {
        $this->close->handle($event->payment->id);
    }
}
