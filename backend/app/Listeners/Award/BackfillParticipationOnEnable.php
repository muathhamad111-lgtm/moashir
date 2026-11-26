<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Events\Award\ParticipationCertificatesEnabled;
use App\Jobs\Award\BackfillParticipationCertificatesJob;

final readonly class BackfillParticipationOnEnable
{
    public function handle(ParticipationCertificatesEnabled $event): void
    {
        BackfillParticipationCertificatesJob::dispatch($event->cycle->id);
    }
}
