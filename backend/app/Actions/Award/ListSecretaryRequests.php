<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\SecretaryRequestFilters;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Services\Award\SecretaryRequestsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListSecretaryRequests
{
    public function __construct(private SecretaryRequestsQuery $requests) {}

    /**
     * @return LengthAwarePaginator<int, Assessment>
     */
    public function handle(AwardCycle $cycle, SecretaryRequestFilters $filters): LengthAwarePaginator
    {
        $query = $this->requests->filtered($cycle, $filters)
            ->with(['entity', 'region', 'currentResult', 'committeeAverage'])
            ->withCount([
                'committeeAssignments as live_assignments_count' => static fn (Builder $rows): Builder => $rows->where('status', '<>', CommitteeAssignmentStatus::Voided),
                'committeeAssignments as completed_assignments_count' => static fn (Builder $rows): Builder => $rows->where('status', CommitteeAssignmentStatus::Completed),
                'committeeAssignments as pending_substitutions_count' => static fn (Builder $rows): Builder => $rows
                    ->where('status', '<>', CommitteeAssignmentStatus::Voided)
                    ->whereNotNull('replaces_assignment_id')
                    ->whereNull('substitute_approved_at'),
            ]);

        return $this->requests->sorted($query, $filters->sort)->paginate($filters->perPage);
    }
}
