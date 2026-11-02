<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Actions\Assessments\CloseRefundedAccreditedRequest;
use App\Enums\Finance\PaymentPurpose;
use App\Events\Finance\PaymentRefunded;
use Illuminate\Contracts\Queue\ShouldQueue;

final class CloseAccreditedRequestOnRefund implements ShouldQueue
{
    public int $tries = 5;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(private readonly CloseRefundedAccreditedRequest $close) {}

    public function shouldQueue(PaymentRefunded $event): bool
    {
        return $event->payment->purpose === PaymentPurpose::Accredited;
    }

    public function handle(PaymentRefunded $event): void
    {
        $this->close->handle($event->payment->id);
    }
}
