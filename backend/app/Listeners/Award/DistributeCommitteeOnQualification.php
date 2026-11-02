<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Actions\Award\DistributeQualifiedRequest;
use App\Events\Award\EntityQualified;

final readonly class DistributeCommitteeOnQualification
{
    public function __construct(private DistributeQualifiedRequest $distribute) {}

    public function handle(EntityQualified $event): void
    {
        $this->distribute->handle($event->assessment, $event->actorId);
    }
}
