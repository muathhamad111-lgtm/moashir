<?php

declare(strict_types=1);

namespace App\Jobs\Finance;

use App\Actions\Finance\ProcessPaymentWebhooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class ProcessGatewayWebhookJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly int $paymentId) {}

    public function uniqueId(): string
    {
        return 'process-gateway-webhook:'.$this->paymentId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping('payment:'.$this->paymentId)->releaseAfter(15)->expireAfter(180)];
    }

    public function handle(ProcessPaymentWebhooks $process): void
    {
        $process->handle($this->paymentId);
    }
}
