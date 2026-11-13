<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Integrations\Fakes\FakePdfRenderer;
use App\Models\Assessments\AssessmentSummary;
use App\Models\Audit\Audit;
use App\Services\Documents\DocumentHtmlComposer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const ASSESSOR_SUMMARY = "أداء الجهة متوازن في الحوكمة.\nتحتاج الجهة إلى تطوير قياس الأثر.";

beforeEach(function (): void {
    Notification::fake();
});

function summaryUrl(int $assessmentId): string
{
    return "/api/v1/assessments/{$assessmentId}/review/summary";
}

it('lets the accredited assignee save, keep and clear the summary with an audit that never copies the text', function (): void {
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest(publishedAccreditedCriteria(), $assessor);

    switchTo($assessor)->putJson(summaryUrl($assessment->id), ['summary' => '  '.ASSESSOR_SUMMARY.'  '])->assertOk()
        ->assertJsonPath('data.summary.body', ASSESSOR_SUMMARY)
        ->assertJsonPath('data.summary.version', 1);
    switchTo($assessor)->putJson(summaryUrl($assessment->id), ['summary' => ASSESSOR_SUMMARY])->assertOk()->assertJsonPath('data.summary.version', 1);
    switchTo($assessor)->putJson(summaryUrl($assessment->id), ['summary' => 'ملخص معدل.'])->assertOk()->assertJsonPath('data.summary.version', 2);
    switchTo($assessor)->putJson(summaryUrl($assessment->id), ['summary' => ''])->assertOk()->assertJsonPath('data.summary', null);

    $audits = Audit::query()->where('event', AuditEvent::AssessmentSummarySaved->value)->orderBy('id')->get();
    expect($audits)->toHaveCount(3)
        ->and($audits[0]->new_values)->toEqual(['version' => 1, 'cleared' => false])
        ->and($audits[2]->new_values)->toEqual(['version' => 2, 'cleared' => true])
        ->and(json_encode($audits->pluck('new_values')->all(), JSON_UNESCAPED_UNICODE))->not->toContain('الحوكمة')
        ->and(AssessmentSummary::query()->where('assessment_id', $assessment->id)->count())->toBe(2)
        ->and(AssessmentSummary::query()->where('assessment_id', $assessment->id)->current()->exists())->toBeFalse();
});

it('accepts exactly 2,000 characters and refuses 2,001', function (): void {
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest(publishedAccreditedCriteria(), $assessor);

    switchTo($assessor)->putJson(summaryUrl($assessment->id), ['summary' => str_repeat('م', 2000)])->assertOk();
    switchTo($assessor)->putJson(summaryUrl($assessment->id), ['summary' => str_repeat('م', 2001)])->assertUnprocessable()->assertJsonValidationErrors('summary');
    switchTo($assessor)->putJson(summaryUrl($assessment->id), [])->assertUnprocessable()->assertJsonValidationErrors('summary');
});

it('returns the summary in the review for the assignee and in the read-only review of the follower', function (): void {
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest(publishedAccreditedCriteria(), $assessor);
    switchTo($assessor)->putJson(summaryUrl($assessment->id), ['summary' => ASSESSOR_SUMMARY])->assertOk();

    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->assertJsonPath('data.summary.body', ASSESSOR_SUMMARY);
    switchTo(requestFollower())->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()
        ->assertJsonPath('data.mode', 'read_only')
        ->assertJsonPath('data.summary.version', 1);
});

it('refuses the summary to everyone but the assignee', function (): void {
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest(publishedAccreditedCriteria(), $assessor);
    $url = summaryUrl($assessment->id);

    switchTo(requestFollower())->putJson($url, ['summary' => ASSESSOR_SUMMARY])->assertForbidden();
    switchTo(ownerOf($assessment->entity))->putJson($url, ['summary' => ASSESSOR_SUMMARY])->assertForbidden();
    switchTo(assessorUser())->putJson($url, ['summary' => ASSESSOR_SUMMARY])->assertNotFound();
    expect(AssessmentSummary::query()->exists())->toBeFalse();
});

it('freezes the summary outside the review and refuses it on an advanced assessment', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $completed = completedAccreditedRequest($version, $assessor);
    $advanced = startedInitialAssessment(openAwardCycle($version), $version, $assessor);

    switchTo($assessor)->putJson(summaryUrl($completed->id), ['summary' => ASSESSOR_SUMMARY])->assertForbidden();
    switchTo(superAdmin())->putJson(summaryUrl($completed->id), ['summary' => ASSESSOR_SUMMARY, 'reason' => SUPER_ADMIN_REASON])
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal');
    switchTo($assessor)->putJson(summaryUrl($advanced->id), ['summary' => ASSESSOR_SUMMARY])->assertConflict()->assertJsonPath('code', 'assessment.summary_not_applicable');
});

it('requires a reason from the Super Admin and stores it with the version', function (): void {
    $assessment = reviewedAccreditedRequest(publishedAccreditedCriteria(), assessorUser());

    switchTo(superAdmin())->putJson(summaryUrl($assessment->id), ['summary' => ASSESSOR_SUMMARY])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    switchTo(superAdmin())->putJson(summaryUrl($assessment->id), ['summary' => ASSESSOR_SUMMARY, 'reason' => SUPER_ADMIN_REASON])->assertOk();

    expect(DB::table('assessment_summaries')->where('assessment_id', $assessment->id)->value('change_reason'))->toBe(SUPER_ADMIN_REASON);
});

it('refuses the summary on a frozen request', function (): void {
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest(publishedAccreditedCriteria(), $assessor);
    $assessment->entity->delete();

    switchTo($assessor)->putJson(summaryUrl($assessment->id), ['summary' => ASSESSOR_SUMMARY])->assertConflict()->assertJsonPath('code', 'entity.deleted');
});

it('keeps the summary rows append-only in the database', function (): void {
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest(publishedAccreditedCriteria(), $assessor);
    switchTo($assessor)->putJson(summaryUrl($assessment->id), ['summary' => ASSESSOR_SUMMARY])->assertOk();
    $id = AssessmentSummary::query()->sole()->id;

    expect(fn () => DB::transaction(fn () => DB::table('assessment_summaries')->where('id', $id)->update(['body' => 'نص معدل'])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_summaries')->where('id', $id)->delete()))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::statement('TRUNCATE assessment_summaries')))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_summaries')->insert([
            'assessment_id' => $assessment->id, 'body' => 'نسخة ثانية حالية', 'author_user_id' => $assessor->id, 'version' => 2, 'created_at' => now(),
        ])))
        ->toThrow(QueryException::class, 'assessment_summaries_current');
});

it('completes without a summary and shows a filled summary in the feedback report and its PDF', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $without = completedAccreditedRequest($version, $assessor);
    $with = scoredAccreditedReview($version, $assessor);
    switchTo($assessor)->putJson(summaryUrl($with->id), ['summary' => ASSESSOR_SUMMARY])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$with->id}/complete")->assertOk();

    switchTo(ownerOf($without->entity))->getJson("/api/v1/assessments/{$without->id}/feedback-report")->assertOk()->assertJsonPath('data.assessor_summary', null);
    $owner = ownerOf($with->entity);
    $response = switchTo($owner)->getJson("/api/v1/assessments/{$with->id}/feedback-report")->assertOk()
        ->assertJsonPath('data.assessor_summary.body', ASSESSOR_SUMMARY);
    $url = (string) switchTo($owner)->postJson("/api/v1/assessments/{$with->id}/feedback-report/export", ['locale' => 'en'])->json('data.url');
    switchTo($owner)->get($url)->assertOk();
    $html = app(DocumentHtmlComposer::class)->compose(collect(app(FakePdfRenderer::class)->rendered())->last());

    expect($response->getContent())->not->toContain($assessor->name)
        ->and($html)->toContain('Assessor summary')
        ->and($html)->toContain('تحتاج الجهة إلى تطوير قياس الأثر.');
});
