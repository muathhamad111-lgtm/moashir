<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Contracts\Criteria\CycleCriteriaBindings;
use App\Data\Criteria\PublishInstruction;
use App\Data\Criteria\PublishOutcome;
use App\Enums\Criteria\DraftCyclesChoice;
use App\Exceptions\Criteria\CriteriaVersionRejected;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaConsistencyChecker;
use App\Services\Criteria\CriteriaEditScopeRules;
use App\Services\Criteria\CriteriaPublication;
use App\Services\Criteria\CriteriaTreeLoader;
use App\Services\Criteria\DraftCriteriaTransaction;
use Illuminate\Support\Facades\DB;

final readonly class PublishCriteriaVersion
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private CriteriaTreeLoader $loader,
        private CriteriaConsistencyChecker $checker,
        private CriteriaEditScopeRules $rules,
        private CriteriaPublication $publication,
        private CycleCriteriaBindings $cycles,
    ) {}

    public function handle(User $actor, CriteriaVersion $version, PublishInstruction $instruction): PublishOutcome
    {
        return DB::transaction(function () use ($actor, $version, $instruction): PublishOutcome {
            $draft = $this->loader->load($this->transaction->lockDraft($version->id));
            $report = $this->checker->check($draft);

            if (! $report->isConsistent()) {
                throw CriteriaVersionRejected::inconsistent($report);
            }

            $previous = CriteriaVersion::query()->published()->lockForUpdate()->first();
            $scope = $this->rules->scopeFor($previous instanceof CriteriaVersion, $instruction->scope);

            if ($previous instanceof CriteriaVersion) {
                $this->rules->assertReason($actor, $instruction->reason);
            }

            $outcome = $this->publication->publish($actor, $draft, $previous, new PublishInstruction($scope, $instruction->reason));
            $moved = $instruction->draftCycles === DraftCyclesChoice::Move ? $this->cycles->moveDrafts($draft) : [];

            return new PublishOutcome($outcome->version, $outcome->editEvent, $outcome->effect, $outcome->changeCount, $this->cycles->summary(), $moved);
        });
    }
}
