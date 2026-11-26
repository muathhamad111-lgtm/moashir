<?php

declare(strict_types=1);

namespace App\Jobs\Nda;

use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\TermsVersion;
use App\Services\Nda\NdaNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Queues the agreement notices for one version. Each round is dispatched once by the row that records it (the
 * publication, or a reminder row), and the job runs once (no retries) so a partly sent round is never sent twice.
 * A version that is no longer current is skipped, since its notice would point to text nobody can accept.
 */
final class SendNdaNotices implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $termsVersionId, public readonly NotificationCode $code) {}

    public function handle(NdaNotifier $notifier): void
    {
        $version = TermsVersion::query()->find($this->termsVersionId);

        if ($version instanceof TermsVersion && $version->is_current) {
            $notifier->notifyPending($version, $this->code);
        }
    }
}
