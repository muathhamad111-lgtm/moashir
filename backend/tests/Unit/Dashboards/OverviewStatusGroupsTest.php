<?php

declare(strict_types=1);

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Enums\Dashboards\OverviewBreakdownKey;
use App\Enums\Dashboards\PaymentStatusGroup;
use App\Enums\Dashboards\RequestStatusGroup;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Identity\Ability;

it('groups each request status of each path as the contract lists it', function (AssessmentPath $path, string $status, ?RequestStatusGroup $group): void {
    expect(RequestStatusGroup::of($path, $status))->toBe($group);
})->with([
    'self awaiting payment' => [AssessmentPath::SelfAssessment, 'awaiting_payment', RequestStatusGroup::AwaitingPayment],
    'self draft' => [AssessmentPath::SelfAssessment, 'draft', RequestStatusGroup::AwaitingPayment],
    'self submitted completes' => [AssessmentPath::SelfAssessment, 'submitted', RequestStatusGroup::Completed],
    'self result' => [AssessmentPath::SelfAssessment, 'result_available', RequestStatusGroup::Completed],
    'self refunded' => [AssessmentPath::SelfAssessment, 'refunded', RequestStatusGroup::Declined],
    'accredited submitted queues' => [AssessmentPath::Accredited, 'submitted', RequestStatusGroup::InQueue],
    'accredited assigned' => [AssessmentPath::Accredited, 'assigned', RequestStatusGroup::UnderReview],
    'accredited under review' => [AssessmentPath::Accredited, 'under_review', RequestStatusGroup::UnderReview],
    'accredited needs completion' => [AssessmentPath::Accredited, 'needs_completion', RequestStatusGroup::NeedsCompletion],
    'accredited completed' => [AssessmentPath::Accredited, 'completed', RequestStatusGroup::Completed],
    'accredited declined' => [AssessmentPath::Accredited, 'declined_incomplete', RequestStatusGroup::Declined],
    'advanced awaiting assignment' => [AssessmentPath::Advanced, 'awaiting_review_assignment', RequestStatusGroup::InQueue],
    'advanced draft' => [AssessmentPath::Advanced, 'draft', RequestStatusGroup::AwaitingPayment],
    'advanced filtering' => [AssessmentPath::Advanced, 'awaiting_filtering', RequestStatusGroup::UnderReview],
    'advanced returned' => [AssessmentPath::Advanced, 'returned_to_committee', RequestStatusGroup::UnderReview],
    'advanced not qualified' => [AssessmentPath::Advanced, 'not_qualified', RequestStatusGroup::Completed],
    'advanced national winner' => [AssessmentPath::Advanced, 'national_winner', RequestStatusGroup::Completed],
    'advanced withdrawn' => [AssessmentPath::Advanced, 'withdrawn', RequestStatusGroup::Declined],
    'advanced rejected conditions' => [AssessmentPath::Advanced, 'rejected_conditions', RequestStatusGroup::Declined],
    'self completed is unknown' => [AssessmentPath::SelfAssessment, 'completed', null],
    'advanced submitted is unknown' => [AssessmentPath::Advanced, 'submitted', null],
    'an unknown status' => [AssessmentPath::Accredited, 'archived', null],
]);

it('places every real request status of every path in exactly one group', function (): void {
    $states = [
        AssessmentPath::SelfAssessment->value => SelfAssessmentStatus::cases(),
        AssessmentPath::Accredited->value => AccreditedAssessmentStatus::cases(),
        AssessmentPath::Advanced->value => AdvancedAssessmentStatus::cases(),
    ];

    foreach ($states as $path => $cases) {
        foreach ($cases as $state) {
            $groups = array_filter(RequestStatusGroup::cases(), static fn (RequestStatusGroup $group): bool => in_array($state->value, $group->statusesOn(AssessmentPath::from($path)), true));

            expect($groups)->toHaveCount(1);
        }
    }
});

it('orders the request groups as the contract lists them', function (): void {
    expect(array_column(RequestStatusGroup::cases(), 'value'))
        ->toBe(['awaiting_payment', 'in_queue', 'under_review', 'needs_completion', 'completed', 'declined']);
});

it('groups each payment status', function (PaymentStatus $status, PaymentStatusGroup $group): void {
    expect(PaymentStatusGroup::of($status))->toBe($group);
})->with([
    [PaymentStatus::Paid, PaymentStatusGroup::Paid],
    [PaymentStatus::Created, PaymentStatusGroup::Pending],
    [PaymentStatus::Pending, PaymentStatusGroup::Pending],
    [PaymentStatus::UnderReview, PaymentStatusGroup::UnderReview],
    [PaymentStatus::Failed, PaymentStatusGroup::Failed],
    [PaymentStatus::Rejected, PaymentStatusGroup::Failed],
    [PaymentStatus::Cancelled, PaymentStatusGroup::Cancelled],
    [PaymentStatus::Refunded, PaymentStatusGroup::Refunded],
]);

it('orders the payment groups and the breakdown keys as the contract lists them', function (): void {
    expect(array_column(PaymentStatusGroup::cases(), 'value'))->toBe(['paid', 'pending', 'under_review', 'failed', 'cancelled', 'refunded'])
        ->and(array_column(OverviewBreakdownKey::cases(), 'value'))->toBe([
            'requests_by_status', 'payments_by_status', 'entities_by_type', 'entities_by_size', 'entities_by_region',
            'assessor_workload', 'kpi_targets', 'finance', 'award', 'inbox', 'users', 'storage',
        ]);
});

it('keeps only the storage breakdown behind an ability', function (): void {
    $gated = array_filter(OverviewBreakdownKey::cases(), static fn (OverviewBreakdownKey $key): bool => $key->requiredAbility() instanceof Ability);

    expect(array_values($gated))->toBe([OverviewBreakdownKey::Storage])
        ->and(OverviewBreakdownKey::Storage->requiredAbility())->toBe(Ability::ViewStorageUsage);
});
