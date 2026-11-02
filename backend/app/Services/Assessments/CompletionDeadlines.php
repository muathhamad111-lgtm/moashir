<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Entities\FreezableDeadlineSource;
use App\Data\Entities\FreezableDeadline;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Entities\Entity;
use Illuminate\Database\Eloquent\Builder;

final class CompletionDeadlines implements FreezableDeadlineSource
{
    private const string DEADLINE_AT = 'deadline_at';

    public function runningDeadlinesOf(Entity $entity): array
    {
        return array_values(AssessmentCompletionRequest::query()
            ->open()
            ->whereHas('assessment', static fn (Builder $assessment): Builder => $assessment->ownedBy($entity->id))
            ->orderBy('id')
            ->get()
            ->map(static fn (AssessmentCompletionRequest $request): FreezableDeadline => new FreezableDeadline($request, self::DEADLINE_AT, $request->deadline_at))
            ->all());
    }
}
