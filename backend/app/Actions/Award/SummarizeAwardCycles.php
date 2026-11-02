<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\CycleListFilters;
use App\Enums\Award\AwardCycleStatus;
use App\Models\Identity\User;
use App\Services\Award\CycleListQuery;
use App\Support\ScalarValue;

final readonly class SummarizeAwardCycles
{
    public function __construct(private CycleListQuery $cycles) {}

    /**
     * @return array{total: int, by_status: array<string, int>}
     */
    public function handle(User $viewer, CycleListFilters $filters): array
    {
        $counts = $this->cycles->filtered($viewer, $filters->withoutStatus())->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $byStatus = [];

        foreach (AwardCycleStatus::cases() as $status) {
            $byStatus[$status->value] = ScalarValue::int($counts->get($status->value));
        }

        return ['total' => array_sum($byStatus), 'by_status' => $byStatus];
    }
}
