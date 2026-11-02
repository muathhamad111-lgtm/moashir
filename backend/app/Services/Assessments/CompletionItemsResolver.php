<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\BoundSubCriteria;
use App\Data\Assessments\CompletionInstruction;
use App\Enums\Assessments\CompletionItemKind;
use App\Exceptions\Assessments\AssessmentRejected;

final class CompletionItemsResolver
{
    /**
     * @return list<array{sub_code: string, kind: string, note: string|null}>
     */
    public function resolve(BoundSubCriteria $subs, CompletionInstruction $instruction): array
    {
        $items = [];

        foreach ($instruction->items as $index => $item) {
            $sub = $subs->withId($item['sub_criterion_id']) ?? throw AssessmentRejected::subNotInVersion('items.'.$index.'.sub_criterion_id');

            if ($item['kind'] === CompletionItemKind::Cv->value && ! $sub->hasCv) {
                throw AssessmentRejected::cvNotAllowed('items.'.$index.'.kind');
            }

            $items[] = ['sub_code' => $sub->code, 'kind' => $item['kind'], 'note' => $item['note']];
        }

        return $items;
    }
}
