<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Models\Award\AwardCycle;
use App\Services\Award\CurrentCycleReader;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListOpenAwardCycles
{
    public function __construct(private CurrentCycleReader $cycles) {}

    /**
     * @return Collection<int, AwardCycle>
     */
    public function handle(): Collection
    {
        return $this->cycles->openForSubmission();
    }
}
