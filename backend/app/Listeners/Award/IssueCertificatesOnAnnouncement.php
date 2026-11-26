<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Events\Award\CycleResultsAnnounced;
use App\Jobs\Award\IssueCycleCertificatesJob;

final readonly class IssueCertificatesOnAnnouncement
{
    public function handle(CycleResultsAnnounced $event): void
    {
        IssueCycleCertificatesJob::dispatch($event->cycle->id);
    }
}
