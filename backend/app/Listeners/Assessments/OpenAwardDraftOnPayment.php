<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Actions\Assessments\OpenPaidAwardDraft;
use App\Enums\Finance\PaymentPurpose;
use App\Events\Finance\PaymentPaid;
use App\Services\Assessments\PaidRequestAlerts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

final class OpenAwardDraftOnPayment implements ShouldQueue
{
    public int $tries = 5;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        private readonly OpenPaidAwardDraft $open,
        private readonly PaidRequestAlerts $alerts,
    ) {}

    public function shouldQueue(PaymentPaid $event): bool
    {
        return $event->payment->purpose === PaymentPurpose::Award;
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
