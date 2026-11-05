<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Assessments\AssessmentState;
use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Data\Dashboards\ActionItemContext;
use App\Enums\Assessments\AccreditedAssessmentStatus as Accredited;
use App\Enums\Assessments\AdvancedAssessmentStatus as Advanced;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\SelfAssessmentStatus as SelfStatus;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Dashboards\ActionSeverity;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Assessments\SubmissionProgressReader;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\DashboardLinks as Links;
use App\Support\ScalarValue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final readonly class EntityActionItems implements ActionItemSource
{
    private const array ACTIONABLE_STATES = [
        [AssessmentPath::SelfAssessment, SelfStatus::Draft],
        [AssessmentPath::Accredited, Accredited::Draft],
        [AssessmentPath::Accredited, Accredited::NeedsCompletion],
        [AssessmentPath::Advanced, Advanced::NeedsCompletion],
    ];

    public function __construct(private SubmissionProgressReader $progress) {}

    public function isAvailableTo(User $user): bool
    {
        return $user->entity_id !== null;
    }

    public function itemsFor(User $user): array
    {
        $entityId = (int) $user->entity_id;

        return array_values(array_filter(
            [$this->profileItem($entityId), ...$this->requestItems($entityId), $this->advancedDraftItem($entityId), $this->paymentItem($entityId)],
            static fn (?ActionItem $item): bool => $item instanceof ActionItem && $item->count > 0,
        ));
    }

    private function profileItem(int $entityId): ?ActionItem
    {
        $entity = Entity::query()->with('owner')->find($entityId);

        if (! $entity instanceof Entity) {
            return null;
        }

        $completion = $entity->profileCompletion();

        return $completion->isComplete()
            ? null
            : new ActionItem('complete_profile', 1, ActionSeverity::Warning, Links::ENTITY_PROFILE, null, ActionItemContext::forProfile($completion->percent()));
    }

    /**
     * @return list<ActionItem>
     */
    private function requestItems(int $entityId): array
    {
        $requests = $this->actionableRequests($entityId);
        $selfDrafts = $this->inState($requests, SelfStatus::Draft);
        $accreditedDrafts = $this->inState($requests, Accredited::Draft);
        $accreditedCompletions = $this->inState($requests, Accredited::NeedsCompletion);
        $advancedCompletions = $this->inState($requests, Advanced::NeedsCompletion);
        $completionDue = $this->completionDue($entityId);

        return [
            new ActionItem('submit_self_draft', $selfDrafts->count(), ActionSeverity::Info, Links::SELF_ASSESSMENT, null, $this->requestContext($selfDrafts)),
            new ActionItem('submit_accredited_draft', $accreditedDrafts->count(), ActionSeverity::Warning, Links::ACCREDITED_ASSESSMENT, null, $this->draftContext($accreditedDrafts)),
            new ActionItem('complete_accredited_request', $accreditedCompletions->count(), ActionSeverity::Danger, Links::ACCREDITED_ASSESSMENT, $completionDue, $this->requestContext($accreditedCompletions)),
            new ActionItem('complete_advanced_request', $advancedCompletions->count(), ActionSeverity::Danger, Links::ADVANCED_ASSESSMENT, $completionDue, ActionItemContext::none()),
        ];
    }

    /**
     * @return Collection<int, Assessment>
     */
    private function actionableRequests(int $entityId): Collection
    {
        return Assessment::query()
            ->ownedBy($entityId)
            ->where(static function (Builder $query): void {
                foreach (self::ACTIONABLE_STATES as [$path, $state]) {
                    $query->orWhere(static fn (Builder $byState): Builder => $byState->where('path', $path)->where('status', $state->value));
                }
            })
            ->orderBy('id')
            ->get(['id', 'request_number', 'path', 'status', 'criteria_version_id']);
    }

    /**
     * @param  Collection<int, Assessment>  $requests
     * @return Collection<int, Assessment>
     */
    private function inState(Collection $requests, AssessmentState $state): Collection
    {
        return $requests->filter(static fn (Assessment $request): bool => $request->status === $state)->values();
    }

    /**
     * @param  Collection<int, Assessment>  $requests
     */
    private function requestContext(Collection $requests): ActionItemContext
    {
        $single = $requests->count() === 1 ? $requests->first() : null;

        return $single instanceof Assessment ? ActionItemContext::forAssessment($single->id, $single->request_number) : ActionItemContext::none();
    }

    /**
     * @param  Collection<int, Assessment>  $drafts
     */
    private function draftContext(Collection $drafts): ActionItemContext
    {
        $single = $drafts->count() === 1 ? $drafts->first() : null;

        return $single instanceof Assessment
            ? ActionItemContext::forAssessment($single->id, $single->request_number, $this->progress->read($single)?->remainingCount())
            : ActionItemContext::none();
    }

    private function completionDue(int $entityId): ?CarbonInterface
    {
        return $this->moment(AssessmentCompletionRequest::query()
            ->open()
            ->whereIn('assessment_id', Assessment::query()->ownedBy($entityId)->select('id'))
            ->min('deadline_at'));
    }

    private function advancedDraftItem(int $entityId): ActionItem
    {
        $row = AggregateRow::fetch(
            Assessment::query()
                ->ownedBy($entityId)
                ->join('award_cycles', 'award_cycles.id', '=', 'assessments.award_cycle_id')
                ->where('assessments.path', AssessmentPath::Advanced)
                ->where('assessments.status', Advanced::Draft->value)
                ->where('award_cycles.status', AwardCycleStatus::SubmissionOpen)
                ->where('award_cycles.submission_end', '>', Date::now()),
            'count(*) as drafts, min(award_cycles.submission_end) as submission_end',
        );

        return new ActionItem('submit_advanced_draft', ScalarValue::int($row['drafts'] ?? null), ActionSeverity::Warning, Links::ADVANCED_ASSESSMENT, $this->moment($row['submission_end'] ?? null), ActionItemContext::none());
    }

    private function paymentItem(int $entityId): ActionItem
    {
        $earliest = Payment::query()
            ->ownedBy($entityId)
            ->open()
            ->select(['public_id', 'reference', 'expires_at'])
            ->selectRaw('count(*) over () as open_payments')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->first();
        $count = ScalarValue::int($earliest?->getAttribute('open_payments'));
        $context = $earliest instanceof Payment && $count === 1 ? ActionItemContext::forPayment($earliest->public_id, $earliest->reference) : ActionItemContext::none();

        return new ActionItem('complete_payment', $count, ActionSeverity::Warning, Links::MY_PAYMENTS, $earliest?->expires_at, $context);
    }

    private function moment(mixed $value): ?CarbonInterface
    {
        return is_string($value) && $value !== '' ? Date::parse($value) : null;
    }
}
