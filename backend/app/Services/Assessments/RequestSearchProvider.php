<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Search\SearchProvider;
use App\Data\Assessments\RequestSearchHit;
use App\Data\Search\SearchQuery;
use App\Data\Search\SearchResultGroup;
use App\Enums\Identity\PermissionKey;
use App\Enums\Search\SearchResultType;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Entities\EntityFreezeGuard;

final readonly class RequestSearchProvider implements SearchProvider
{
    public function __construct(private AssessmentRecordScope $scope, private EntityFreezeGuard $freezeGuard) {}

    public function type(): SearchResultType
    {
        return SearchResultType::Requests;
    }

    public function isAvailableTo(User $actor): bool
    {
        return $actor->can(PermissionKey::RequestsQueueView->value)
            || $actor->can(PermissionKey::RequestsAssignedView->value);
    }

    public function search(User $actor, SearchQuery $query): SearchResultGroup
    {
        $matches = $this->freezeGuard->excludeFrozen(Assessment::query()->matchingSearch($query->term));
        $this->scope->restrict($matches, $actor);

        $hits = (clone $matches)
            ->with(['entity', 'awardCycle'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($query->limit)
            ->get()
            ->map(static fn (Assessment $assessment): RequestSearchHit => RequestSearchHit::fromAssessment($assessment, $actor))
            ->all();

        return new SearchResultGroup($this->type(), $matches->count(), array_values($hits));
    }
}
