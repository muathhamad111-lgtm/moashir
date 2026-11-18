<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Enums\Award\CommitteeDiscussionStatus;
use App\Models\Identity\User;
use App\Services\Award\MyCommitteeDiscussionsQuery;
use App\Support\ScalarValue;

/**
 * Counts the viewer's committee discussions per status for the list tabs.
 */
final readonly class CountMyCommitteeDiscussions
{
    public function __construct(private MyCommitteeDiscussionsQuery $discussions) {}

    /**
     * @return array<string, int>
     */
    public function handle(User $viewer): array
    {
        $counts = $this->discussions->for($viewer)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $statuses = CommitteeDiscussionStatus::cases();

        return array_combine(
            array_map(static fn (CommitteeDiscussionStatus $status): string => $status->value, $statuses),
            array_map(static fn (CommitteeDiscussionStatus $status): int => ScalarValue::int($counts[$status->value] ?? null), $statuses),
        );
    }
}
