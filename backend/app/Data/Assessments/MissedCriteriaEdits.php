<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Data\Criteria\CriteriaEditEffect;
use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Criteria\CriteriaEditScope;
use App\Models\Criteria\CriteriaEditEvent;

final readonly class MissedCriteriaEdits
{
    /**
     * @param  list<CriteriaEditEvent>  $edits
     */
    public function __construct(public array $edits) {}

    public function targetVersionId(): ?int
    {
        $last = array_key_last($this->edits);

        return $last === null ? null : $this->edits[$last]->to_version_id;
    }

    public function scope(): CriteriaEditScope
    {
        $strictest = CriteriaEditScope::NewOnly;

        foreach ($this->edits as $edit) {
            if ($edit->scope->isStricterThan($strictest)) {
                $strictest = $edit->scope;
            }
        }

        return $strictest;
    }

    public function effect(): CriteriaEditEffect
    {
        return new CriteriaEditEffect(CriteriaEditKind::Structural, $this->scope());
    }

    public function rebindsOpenAssessments(): bool
    {
        return $this->targetVersionId() !== null && $this->effect()->rebindsOpenAssessments();
    }

    /**
     * @return list<string>
     */
    public function rescoredCodes(): array
    {
        $codes = [];

        foreach ($this->edits as $edit) {
            foreach ($edit->changes as $change) {
                if (($change['score_affecting'] ?? true) !== false && is_string($change['code'] ?? null)) {
                    $codes[$change['code']] = $change['code'];
                }
            }
        }

        sort($codes);

        return $codes;
    }

    /**
     * @return list<int>
     */
    public function editEventIds(): array
    {
        return array_map(static fn (CriteriaEditEvent $edit): int => $edit->id, $this->edits);
    }
}
