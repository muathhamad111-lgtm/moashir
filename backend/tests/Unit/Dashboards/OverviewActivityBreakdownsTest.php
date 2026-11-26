<?php

declare(strict_types=1);

use App\Data\Dashboards\BoardFilter;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Assessments\Assessment;
use App\Models\Finance\CheckoutIntent;
use App\Models\Finance\Payment;
use App\Services\Dashboards\Breakdowns\AssessorWorkloadBreakdown;
use App\Services\Dashboards\Breakdowns\AwardCycleBreakdown;
use App\Services\Dashboards\Breakdowns\FinanceBreakdown;
use App\Services\Dashboards\Breakdowns\PaymentStatusBreakdown;
use App\Services\Dashboards\Breakdowns\RequestStatusBreakdown;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-17 08:00:00');
});

it('counts the requests created in the period by their status group, every group present', function (): void {
    $version = publishedAccreditedCriteria();
    Assessment::factory()->boundTo($version)->count(2)->create();
    Assessment::factory()->boundTo($version)->create()->forceFill(['created_at' => '2026-10-20 09:00:00'])->saveQuietly();
    submittedAccreditedRequest($version);

    expect(breakdownOf(RequestStatusBreakdown::class, 'requests_by_status'))->toBe([
        ['key' => 'awaiting_payment', 'count' => 2],
        ['key' => 'in_queue', 'count' => 1],
        ['key' => 'under_review', 'count' => 0],
        ['key' => 'needs_completion', 'count' => 0],
        ['key' => 'completed', 'count' => 0],
        ['key' => 'declined', 'count' => 0],
    ])->and(collect(breakdownOf(RequestStatusBreakdown::class, 'requests_by_status', new BoardFilter(path: AssessmentPath::Accredited)))->pluck('count', 'key')->all())
        ->toMatchArray(['awaiting_payment' => 0, 'in_queue' => 1]);
});

it('counts the live payments created in the period by their status group', function (): void {
    storedPaidPayment();
    Payment::factory()->create();
    Payment::factory()->create(['purpose' => PaymentPurpose::SelfAssessment]);
    Payment::factory()->testMode()->create();
    Payment::factory()->create()->forceFill(['created_at' => '2026-10-20 09:00:00'])->saveQuietly();

    expect(collect(breakdownOf(PaymentStatusBreakdown::class, 'payments_by_status'))->pluck('count', 'key')->all())
        ->toBe(['paid' => 1, 'pending' => 2, 'under_review' => 0, 'failed' => 0, 'cancelled' => 0, 'refunded' => 0])
        ->and(collect(breakdownOf(PaymentStatusBreakdown::class, 'payments_by_status', new BoardFilter(path: AssessmentPath::SelfAssessment)))->pluck('count', 'key')->all())
        ->toMatchArray(['paid' => 0, 'pending' => 1]);
});

it('ranks the assessors by their open and completed assignments', function (): void {
    $version = publishedAccreditedCriteria();
    $busy = assessorUser();
    $finished = assessorUser();
    assignedAccreditedRequest($version, $busy);
    completedAccreditedRequest($version, $finished);
    assessorUser();

    expect(breakdownOf(AssessorWorkloadBreakdown::class, 'assessor_workload'))->toBe([
        ['key' => (string) $busy->id, 'name' => $busy->name, 'open' => 1, 'completed' => 0],
        ['key' => (string) $finished->id, 'name' => $finished->name, 'open' => 0, 'completed' => 1],
    ])->and(breakdownOf(AssessorWorkloadBreakdown::class, 'assessor_workload', new BoardFilter(path: AssessmentPath::Advanced)))->toBe([]);
});

it('keeps the assessors to the top eight', function (): void {
    $version = publishedAccreditedCriteria();

    for ($index = 0; $index < 9; $index++) {
        assignedAccreditedRequest($version, assessorUser());
    }

    expect(breakdownOf(AssessorWorkloadBreakdown::class, 'assessor_workload'))->toHaveCount(8);
});

it('sums the VAT, refunds, receipts, transfers and abandoned carts of the period', function (): void {
    $payment = storedPaidPayment();
    storedPaidPayment(null, IntegrationMode::Live, PaymentPurpose::SelfAssessment);
    $old = storedPaidPayment();
    $old->forceFill(['paid_at' => '2026-10-10 10:00:00'])->saveQuietly();
    switchTo(financeOfficer())->post("/api/v1/finance/payments/{$payment->public_id}/refunds", refundPayload($payment), ['Accept' => 'application/json'])->assertCreated();
    Payment::factory()->bankTransfer()->create();
    CheckoutIntent::query()->create(['entity_id' => profiledEntity()->id, 'purpose' => PaymentPurpose::Accredited, 'last_seen_at' => '2026-11-10 09:00:00']);
    CheckoutIntent::query()->create(['entity_id' => profiledEntity()->id, 'purpose' => PaymentPurpose::Accredited, 'last_seen_at' => '2026-10-10 09:00:00']);

    expect(breakdownOf(FinanceBreakdown::class, 'finance'))->toBe([
        'vat_collected' => '150.00',
        'refunds_total' => '1150.00',
        'receipts_issued' => 3,
        'transfers_awaiting_review' => 1,
        'abandoned_carts' => 1,
    ])->and(breakdownOf(FinanceBreakdown::class, 'finance', new BoardFilter(path: AssessmentPath::SelfAssessment)))->toBe([
        'vat_collected' => '150.00',
        'refunds_total' => '0.00',
        'receipts_issued' => 1,
        'transfers_awaiting_review' => 0,
        'abandoned_carts' => 0,
    ]);
});

it('summarises the newest award cycle overlapping the period, or nothing', function (): void {
    expect(breakdownOf(AwardCycleBreakdown::class, 'award'))->toBeNull();

    [$cycle, $version, , $officer] = committeeCycle();
    $qualified = initiallyAssessedRequest($cycle, $version, assessorUser());
    initiallyAssessedRequest($cycle, $version, assessorUser());
    advancedDraft($cycle);
    closeCycleSubmissions($cycle);
    qualifyRequest($cycle, $officer, $qualified);

    expect(breakdownOf(AwardCycleBreakdown::class, 'award'))->toBe([
        'cycle_year' => $cycle->year,
        'status' => $cycle->refresh()->status->value,
        'submission_closed_on' => $cycle->submission_end->setTimezone('Asia/Riyadh')->toDateString(),
        'announcement_on' => $cycle->announcement_at?->setTimezone('Asia/Riyadh')->toDateString(),
        'applicants' => 2,
        'qualified' => 1,
    ])->and(breakdownOf(AwardCycleBreakdown::class, 'award', new BoardFilter(region: 'nowhere'))['applicants'])->toBe(0);
});
