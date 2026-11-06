<?php

declare(strict_types=1);

namespace App\Data\Demo;

final readonly class DemoInstallation
{
    public function __construct(public DemoPeople $people, public DemoActivity $activity, public DemoContent $content) {}

    public function summary(): string
    {
        return sprintf(
            '%d staff accounts, %d entities with their owner accounts, %d TEST-mode payments, %d accredited demo results, one draft award cycle (id %d), %s',
            count($this->people->staff),
            count($this->people->entityOwners),
            count($this->activity->payments),
            $this->activity->accreditedResults,
            $this->activity->cycle->id,
            $this->content->summary(),
        );
    }
}
