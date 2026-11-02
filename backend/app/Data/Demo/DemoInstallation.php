<?php

declare(strict_types=1);

namespace App\Data\Demo;

final readonly class DemoInstallation
{
    public function __construct(public DemoPeople $people, public DemoActivity $activity) {}

    public function summary(): string
    {
        return sprintf(
            '%d staff accounts, %d entities with their owner accounts, %d TEST-mode payments and one draft award cycle (id %d)',
            count($this->people->staff),
            count($this->people->entityOwners),
            count($this->activity->payments),
            $this->activity->cycle->id,
        );
    }
}
