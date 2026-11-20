<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Mail\RequestedItem;
use App\Data\Mail\RequestedItems;
use App\Enums\Assessments\CompletionItemKind;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;

/**
 * The items of a completion request as the entity's e-mail lists them: each stored main criterion or sub-criterion
 * code, named from the request's own criteria version.
 */
final readonly class CompletionRequestItems
{
    public function __construct(private BoundSubCriteriaReader $subs) {}

    public function of(Assessment $assessment, AssessmentCompletionRequest $request): RequestedItems
    {
        $subs = $this->subs->of($assessment->loadMissing('criteriaVersion')->criteriaVersion);

        return new RequestedItems(array_map(
            static fn (array $item): RequestedItem => new RequestedItem(
                AssessmentCompletionRequest::codeOf($item),
                $subs->nameOf(AssessmentCompletionRequest::codeOf($item)),
                CompletionItemKind::from($item['kind']),
                $item['note'],
            ),
            $request->items,
        ));
    }
}
