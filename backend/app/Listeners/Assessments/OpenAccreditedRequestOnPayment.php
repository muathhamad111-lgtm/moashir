<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Actions\Assessments\OpenPaidAccreditedRequest;
use App\Enums\Finance\PaymentPurpose;
use App\Events\Finance\PaymentPaid;
use App\Services\Assessments\AccreditedRequestAlerts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

final class OpenAccreditedRequestOnPayment implements ShouldQueue
{
    public int $tries = 5;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        private readonly OpenPaidAccreditedRequest $open,
        private readonly AccreditedRequestAlerts $alerts,
    ) {}

    public function shouldQueue(PaymentPaid $event): bool
    {
        return $event->payment->purpose === PaymentPurpose::Accredited;
    }

    public function handle(PaymentPaid $event): void
    {
        $this->open->handle($event->payment->id);
    }

    public function failed(PaymentPaid $event, Throwable $exception): void
    {
        $this->alerts->openingFailed($event->payment);
    }
}
