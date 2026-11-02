<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\MissedCriteriaEdits;
use App\Enums\Criteria\CriteriaEditKind;
use App\Models\Criteria\CriteriaEditEvent;

final class MissedCriteriaEditsReader
{
    public function missedBy(int $boundVersionId): MissedCriteriaEdits
    {
        return new MissedCriteriaEdits($this->replacementChainFrom($boundVersionId));
    }

    /**
     * @return list<CriteriaEditEvent>
     */
    private function replacementChainFrom(int $versionId): array
    {
        $replacements = $this->replacementsByReplacedVersion($versionId);
        $chain = [];

        while (isset($replacements[$versionId]) && count($chain) < count($replacements)) {
            $chain[] = $replacements[$versionId];
            $versionId = (int) $replacements[$versionId]->to_version_id;
        }

        return $chain;
    }

    /**
     * @return array<int, CriteriaEditEvent>
     */
    private function replacementsByReplacedVersion(int $versionId): array
    {
        $replacements = [];

        $edits = CriteriaEditEvent::query()
            ->where('kind', CriteriaEditKind::Structural)
            ->whereNotNull('to_version_id')
            ->where('from_version_id', '>=', $versionId)
            ->orderBy('id')
            ->get();

        foreach ($edits as $edit) {
            $replacements[$edit->from_version_id] ??= $edit;
        }

        return $replacements;
    }
}
