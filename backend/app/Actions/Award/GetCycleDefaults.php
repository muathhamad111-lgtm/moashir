<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\CycleDefaults;
use App\Services\Award\CycleDefaultsReader;

final readonly class GetCycleDefaults
{
    public function __construct(private CycleDefaultsReader $defaults) {}

    public function handle(): CycleDefaults
    {
        return $this->defaults->read();
    }
}
