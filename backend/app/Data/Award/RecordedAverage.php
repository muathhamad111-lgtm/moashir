<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Models\Award\CommitteeAverage;

final readonly class RecordedAverage
{
    public function __construct(public CommitteeAverage $average, public bool $isNew) {}
}
