<?php

declare(strict_types=1);

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\RoleKey;
use App\Enums\Insights\MaturityBand;
use App\Models\Insights\MaturityInterpretation;
use Illuminate\Support\Facades\Notification;

const FULL_MARKS_70 = ['M1.1' => '70.00', 'M1.2' => '70.00', 'M2.1' => '70.00', 'M2.2' => '70.00'];

beforeEach(function (): void {
    Notification::fake();
});

it('classifies the sub-criteria of the selected accredited result with the assessor texts quoted verbatim', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $assessment = accreditedResultFor($entity, $version, ['M1.1' => '49.99', 'M1.2' => '50.00', 'M2.1' => '79.99', 'M2.2' => '80.00']);
    writeFeedbackFor($assessment, ['M2.2' => ['strength' => 'سياسة معتمدة ومطبّقة.', 'improvement' => 'توثيق المراجعة الدورية.', 'note' => 'ملاحظة داخلية.']]);

    $response = switchTo(ownerOf($entity))->getJson("/api/v1/assessments/{$assessment->id}/insights/strengths-gaps")->assertOk()
        ->assertJsonPath('data.result.assessment_id', $assessment->id)
        ->assertJsonPath('data.result.criteria_version.label.en', 'Test version')
        ->assertJsonPath('data.rules.critical_below', '50.00')
        ->assertJsonPath('data.rules.version', 1)
        ->assertJsonPath('data.counts', ['strength' => 1, 'opportunity' => 2, 'critical_gap' => 1])
        ->assertJsonPath('data.items.critical_gaps.0.sub_code', 'M1.1')
        ->assertJsonPath('data.items.critical_gaps.0.percent', '49.99')
        ->assertJsonPath('data.items.critical_gaps.0.lost_points', '30.01')
        ->assertJsonPath('data.items.critical_gaps.0.assessor.strengths', null)
        ->assertJsonPath('data.items.strengths.0.sub_code', 'M2.2')
        ->assertJsonPath('data.items.strengths.0.assessor.strengths', 'سياسة معتمدة ومطبّقة.')
        ->assertJsonPath('data.items.strengths.0.assessor.improvement_points', 'توثيق المراجعة الدورية.')
        ->assertJsonPath('data.items.opportunities.0.sub_code', 'M1.2')
        ->assertJsonPath('data.items.opportunities.1.sub_code', 'M2.1')
        ->assertJsonPath('data.mains.0.code', 'M1')
        ->assertJsonPath('data.mains.0.class', 'critical_gap');

    expect($response->getContent())->not->toContain('ملاحظة داخلية.');
});

it('orders the opportunities by lost points first and re-classifies at once when the rules change', function (): void {
    $version = insightsCriteria(['M1' => ['M1.1' => '100.00', 'M1.2' => '20.00']]);
    $entity = entityWithOwner();
    $assessment = accreditedResultFor($entity, $version, ['M1.1' => '70.00', 'M1.2' => '55.00']);
    $url = "/api/v1/assessments/{$assessment->id}/insights/strengths-gaps";

    switchTo(ownerOf($entity))->getJson($url)->assertOk()
        ->assertJsonPath('data.items.opportunities.0.sub_code', 'M1.1')
        ->assertJsonPath('data.items.opportunities.1.sub_code', 'M1.2');

    setInsightsRules(gaps: ['critical_below' => '60.00', 'strength_from' => '70.00', 'version' => 2]);

    switchTo(ownerOf($entity))->getJson($url)->assertOk()
        ->assertJsonPath('data.rules.version', 2)
        ->assertJsonPath('data.items.strengths.0.sub_code', 'M1.1')
        ->assertJsonPath('data.items.critical_gaps.0.sub_code', 'M1.2');
});

it('shows the maturity bands with the delta against the previous accredited result', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    accreditedResultFor($entity, $version, ['M1.1' => '40.00', 'M1.2' => '40.00', 'M2.1' => '70.00', 'M2.2' => '70.00'], '2026-03-01 09:00:00');
    $latest = accreditedResultFor($entity, $version, ['M1.1' => '60.00', 'M1.2' => '60.00', 'M2.1' => '80.00', 'M2.2' => '80.00'], '2026-06-01 09:00:00');

    switchTo(ownerOf($entity))->getJson("/api/v1/assessments/{$latest->id}/insights/maturity")->assertOk()
        ->assertJsonPath('data.overall.band', 'mature')
        ->assertJsonPath('data.overall.delta_pp', '15.00')
        ->assertJsonPath('data.overall.previous_band', 'developing')
        ->assertJsonPath('data.versions_differ', false)
        ->assertJsonPath('data.mains.0.band', 'developing')
        ->assertJsonPath('data.mains.0.comparison', 'matched')
        ->assertJsonPath('data.mains.0.delta_pp', '20.00')
        ->assertJsonPath('data.mains.0.description.en', 'Measures M1')
        ->assertJsonPath('data.mains.1.band', 'leading')
        ->assertJsonPath('data.mains.1.previous_band', 'mature')
        ->assertJsonPath('data.mains.1.subs.0.delta_pp', '10.00')
        ->assertJsonPath('data.mains.0.interpretation', null)
        ->assertJsonPath('data.rules.developing_from', '50.00');
});

it('compares an older result with the accredited result just before it and has no delta for the first one', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $first = accreditedResultFor($entity, $version, FULL_MARKS_70, '2026-01-01 09:00:00');
    $second = accreditedResultFor($entity, $version, ['M1.1' => '80.00', 'M1.2' => '80.00', 'M2.1' => '80.00', 'M2.2' => '80.00'], '2026-03-01 09:00:00');
    accreditedResultFor($entity, $version, ['M1.1' => '90.00', 'M1.2' => '90.00', 'M2.1' => '90.00', 'M2.2' => '90.00'], '2026-06-01 09:00:00');
    $owner = ownerOf($entity);

    switchTo($owner)->getJson("/api/v1/assessments/{$second->id}/insights/maturity")->assertOk()
        ->assertJsonPath('data.previous.assessment_id', $first->id)
        ->assertJsonPath('data.overall.delta_pp', '10.00');
    switchTo($owner)->getJson("/api/v1/assessments/{$first->id}/insights/maturity")->assertOk()
        ->assertJsonPath('data.previous', null)
        ->assertJsonPath('data.overall.delta_pp', null)
        ->assertJsonPath('data.mains.0.comparison', 'no_previous');
});

it('matches the delta by code and labels new and removed criteria across criteria versions', function (): void {
    $old = insightsCriteria();
    $entity = entityWithOwner();
    accreditedResultFor($entity, $old, FULL_MARKS_70, '2026-01-01 09:00:00');
    archiveVersion($old);
    $new = insightsCriteria(['M1' => ['M1.1' => '60.00', 'M1.3' => '40.00'], 'M3' => ['M3.1' => '100.00']]);
    $latest = accreditedResultFor($entity, $new, ['M1.1' => '80.00', 'M1.3' => '80.00', 'M3.1' => '80.00'], '2026-06-01 09:00:00');

    switchTo(ownerOf($entity))->getJson("/api/v1/assessments/{$latest->id}/insights/maturity")->assertOk()
        ->assertJsonPath('data.versions_differ', true)
        ->assertJsonPath('data.mains.0.comparison', 'matched')
        ->assertJsonPath('data.mains.0.subs.0.delta_pp', '10.00')
        ->assertJsonPath('data.mains.0.subs.1.code', 'M1.3')
        ->assertJsonPath('data.mains.0.subs.1.delta_pp', null)
        ->assertJsonPath('data.mains.1.code', 'M3')
        ->assertJsonPath('data.mains.1.comparison', 'new_in_version')
        ->assertJsonPath('data.mains.1.delta_pp', null)
        ->assertJsonPath('data.removed_since_previous.0.code', 'M2')
        ->assertJsonPath('data.removed_since_previous.0.previous_percent', '70.00');
});

it('resolves the interpretation text: the override of the criterion wins over the generic band text', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $assessment = accreditedResultFor($entity, $version, ['M1.1' => '85.00', 'M1.2' => '85.00', 'M2.1' => '85.00', 'M2.2' => '85.00']);
    MaturityInterpretation::query()->whereNull('main_criterion_code')->where('band', MaturityBand::Leading)->sole()
        ->forceFill(['text' => ['ar' => 'نص عام للمرحلة الرائدة.', 'en' => 'Generic leading text.']])->save();
    MaturityInterpretation::query()->create(['band' => MaturityBand::Leading, 'main_criterion_code' => 'M2', 'text' => ['ar' => 'نص خاص بالمعيار الثاني.', 'en' => 'M2 leading text.']]);

    switchTo(ownerOf($entity))->getJson("/api/v1/assessments/{$assessment->id}/insights/maturity")->assertOk()
        ->assertJsonPath('data.mains.0.interpretation.en', 'Generic leading text.')
        ->assertJsonPath('data.mains.1.interpretation.en', 'M2 leading text.');
});

it('builds the executive summary from facts and quoted assessor texts only', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    accreditedResultFor($entity, $version, FULL_MARKS_70, '2026-01-01 09:00:00');
    $assessment = accreditedResultFor($entity, $version, ['M1.1' => '85.00', 'M1.2' => '45.00', 'M2.1' => '75.00', 'M2.2' => '90.00'], '2026-06-01 09:00:00');
    $assessor = assessorUser();
    writeFeedbackFor($assessment, ['M2.2' => ['strength' => 'إدارة مالية منضبطة.'], 'M1.2' => ['improvement' => 'بناء خطة تشغيلية سنوية.']], $assessor);
    writeAssessorSummary($assessment, 'أداء الجهة متوازن مع حاجة لتطوير التخطيط.');
    certifyResult($assessment);

    $response = switchTo(ownerOf($entity))->getJson("/api/v1/assessments/{$assessment->id}/insights/executive-summary")->assertOk()
        ->assertJsonPath('data.entity.name.ar', $entity->name->ar)
        ->assertJsonPath('data.score.percent', '75.75')
        ->assertJsonPath('data.certificate.standing', 'level')
        ->assertJsonPath('data.certificate.level', 'silver')
        ->assertJsonPath('data.change.delta_pp', '5.75')
        ->assertJsonPath('data.assessor_summary.body', 'أداء الجهة متوازن مع حاجة لتطوير التخطيط.')
        ->assertJsonPath('data.profile.classes', ['strength' => 2, 'opportunity' => 1, 'critical_gap' => 1])
        ->assertJsonPath('data.strong_areas.0.sub_code', 'M2.2')
        ->assertJsonPath('data.strong_areas.0.quote', 'إدارة مالية منضبطة.')
        ->assertJsonPath('data.priority_gaps.0.sub_code', 'M1.2')
        ->assertJsonPath('data.priority_gaps.0.quote', 'بناء خطة تشغيلية سنوية.')
        ->assertJsonPath('data.priority_gaps.1.sub_code', 'M2.1')
        ->assertJsonCount(2, 'data.strong_areas');

    expect($response->getContent())->not->toContain($assessor->name)
        ->and($response->getContent())->not->toContain((string) $assessor->email);
});

it('marks a participation result and a result without a certificate, and hides an empty assessor summary', function (): void {
    $version = insightsCriteria();
    $participation = accreditedResultFor(entityWithOwner(), $version, ['M1.1' => '40.00', 'M1.2' => '40.00', 'M2.1' => '40.00', 'M2.2' => '40.00']);
    certifyResult($participation);
    $plain = accreditedResultFor(entityWithOwner(), $version, FULL_MARKS_70);

    switchTo(ownerOf($participation->entity))->getJson("/api/v1/assessments/{$participation->id}/insights/executive-summary")->assertOk()
        ->assertJsonPath('data.certificate.standing', 'participation')
        ->assertJsonPath('data.certificate.level', null);
    switchTo(ownerOf($plain->entity))->getJson("/api/v1/assessments/{$plain->id}/insights/executive-summary")->assertOk()
        ->assertJsonPath('data.certificate.standing', 'not_issued')
        ->assertJsonPath('data.assessor_summary', null)
        ->assertJsonPath('data.change.previous', null);
});

it('refuses results that are not eligible with the reason', function (string $case, string $reason): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $assessment = accreditedResultFor($entity, $version, FULL_MARKS_70);

    match ($case) {
        'revoked' => revokeResultCertificate($assessment),
        'reopened' => tap(certifyResult($assessment), static fn () => switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk()),
        'not_completed' => $assessment = insightsAssessment($entity, $version, AssessmentPath::Accredited, AccreditedAssessmentStatus::Submitted),
    };

    foreach (['strengths-gaps', 'maturity', 'executive-summary'] as $tool) {
        switchTo(ownerOf($entity))->getJson("/api/v1/assessments/{$assessment->id}/insights/{$tool}")
            ->assertConflict()
            ->assertJsonPath('code', 'insights.result_not_eligible')
            ->assertJsonPath('meta.reason', $reason);
    }

    switchTo(ownerOf($entity))->postJson("/api/v1/assessments/{$assessment->id}/board-report/export")->assertConflict()->assertJsonPath('meta.reason', $reason);
})->with([
    'revoked certificate' => ['revoked', 'certificate_revoked'],
    'reopened assessment' => ['reopened', 'under_review'],
    'not completed' => ['not_completed', 'not_completed'],
]);

it('refuses an own advanced assessment as not accredited and hides self assessments', function (): void {
    $version = publishedAccreditedCriteria();
    $advanced = submittedAdvancedRequest(openAwardCycle($version), $version);
    archiveVersion($version);
    $self = selfResultFor(entityWithOwner(), insightsCriteria());

    switchTo(ownerOf($advanced->entity))->getJson("/api/v1/assessments/{$advanced->id}/insights/maturity")
        ->assertConflict()
        ->assertJsonPath('meta.reason', 'not_accredited');
    switchTo(ownerOf($self->entity))->getJson("/api/v1/assessments/{$self->id}/insights/maturity")->assertNotFound();
});

it('lets the owner, the global readers and the Super Admin read the tools and hides them from everyone else', function (): void {
    $version = insightsCriteria();
    $assessment = accreditedResultFor(entityWithOwner(), $version, FULL_MARKS_70);
    $url = "/api/v1/assessments/{$assessment->id}/insights/strengths-gaps";

    switchTo(ownerOf($assessment->entity))->getJson($url)->assertOk();
    switchTo(systemAdmin())->getJson($url)->assertOk();
    switchTo(requestFollower())->getJson($url)->assertOk();
    switchTo(followerOfPaths(AssessmentPath::Accredited))->getJson($url)->assertOk();
    switchTo(superAdmin())->getJson($url)->assertOk();
    switchTo(followerOfPaths(AssessmentPath::Advanced))->getJson($url)->assertNotFound();
    switchTo(ownerOf(entityWithOwner()))->getJson($url)->assertNotFound();
    switchTo(assessorUser())->getJson($url)->assertForbidden();
    switchTo(staffUser(RoleKey::ContentOfficer))->getJson($url)->assertForbidden();
    switchTo(staffUser(RoleKey::FinanceOfficer))->getJson($url)->assertForbidden();
    $this->app['auth']->forgetGuards();
    $this->getJson($url)->assertUnauthorized();
});

it('keeps the assessor who reviewed the request out of the tools', function (): void {
    $assessor = assessorUser();
    $assessment = completedAccreditedRequest(publishedAccreditedCriteria(), $assessor);

    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/insights/maturity")->assertForbidden();
    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/insights/maturity")->assertOk();
});
