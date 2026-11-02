<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\CriteriaEditEffect;
use App\Data\Criteria\CriteriaEditRecord;
use App\Data\Criteria\TextCorrectionBatch;
use App\Data\Criteria\TextCorrectionOutcome;
use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Criteria\CriteriaEditScope;
use App\Exceptions\Criteria\CriteriaVersionRejected;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaEditRecorder;
use App\Services\Criteria\CriteriaEditScopeRules;
use App\Services\Criteria\CriteriaTextCorrector;
use Illuminate\Support\Facades\DB;

final readonly class CorrectCriteriaText
{
    public function __construct(
        private CriteriaEditScopeRules $rules,
        private CriteriaTextCorrector $corrector,
        private CriteriaEditRecorder $edits,
    ) {}

    public function handle(User $actor, CriteriaVersion $version, TextCorrectionBatch $batch): TextCorrectionOutcome
    {
        if (! $version->status->acceptsTextCorrections()) {
            throw CriteriaVersionRejected::isDraft();
        }

        $this->rules->assertReason($actor, $batch->reason);

        return DB::transaction(function () use ($actor, $version, $batch): TextCorrectionOutcome {
            $locked = CriteriaVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $changes = $this->corrector->apply($locked, $batch);
            $effect = new CriteriaEditEffect(CriteriaEditKind::TextCorrection, CriteriaEditScope::All);

            if ($changes === []) {
                return new TextCorrectionOutcome($locked, null, $effect, 0);
            }

            $record = new CriteriaEditRecord(CriteriaEditKind::TextCorrection, CriteriaEditScope::All, $locked, null, $changes, $batch->reason);

            return new TextCorrectionOutcome($locked, $this->edits->record($actor, $record), $effect, count($changes));
        });
    }
}
