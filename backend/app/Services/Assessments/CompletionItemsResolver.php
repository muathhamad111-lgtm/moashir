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
     * @return list<array{sub_code?: string, main_code?: string, kind: string, note: string|null}>
     */
    public function resolve(BoundSubCriteria $subs, CompletionInstruction $instruction): array
    {
        $items = [];

        foreach ($instruction->items as $index => $item) {
            $items[] = $item['main_criterion_id'] === null
                ? $this->subItem($subs, $item, 'items.'.$index)
                : $this->mainItem($subs, $item, 'items.'.$index);
        }

        return $items;
    }

    /**
     * @param  array{main_criterion_id: int|null, sub_criterion_id: int|null, kind: string, note: string|null}  $item
     * @return array{sub_code: string, kind: string, note: string|null}
     */
    private function subItem(BoundSubCriteria $subs, array $item, string $field): array
    {
        $sub = $subs->withId($item['sub_criterion_id'] ?? 0) ?? throw AssessmentRejected::subNotInVersion($field.'.sub_criterion_id');

        if ($item['kind'] === CompletionItemKind::Cv->value && ! $sub->hasCv) {
            throw AssessmentRejected::cvNotAllowed($field.'.kind');
        }

        return ['sub_code' => $sub->code, 'kind' => $item['kind'], 'note' => $item['note']];
    }

    /**
     * A main criterion can be asked for its own attachments only.
     *
     * @param  array{main_criterion_id: int|null, sub_criterion_id: int|null, kind: string, note: string|null}  $item
     * @return array{main_code: string, kind: string, note: string|null}
     */
    private function mainItem(BoundSubCriteria $subs, array $item, string $field): array
    {
        $main = $subs->mainWithId((int) $item['main_criterion_id']) ?? throw AssessmentRejected::mainNotInVersion($field.'.main_criterion_id');

        if ($item['kind'] !== CompletionItemKind::Evidence->value) {
            throw AssessmentRejected::cvNotAllowed($field.'.kind');
        }

        return ['main_code' => $main->code, 'kind' => $item['kind'], 'note' => $item['note']];
    }
}
