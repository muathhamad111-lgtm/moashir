<?php

declare(strict_types=1);

use App\Integrations\Fakes\FakePdfRenderer;
use App\Services\Documents\DocumentHtmlComposer;

it('keeps the notes of an accredited request away from every entity response, its completion request and its notifications', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    writeMarkerNotes($assessment, $version, $assessor, 'accredited');
    scoreEveryUnit($assessor, $assessment);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", roundCompletionPayload($version))->assertOk();
    $owner = ownerOf($assessment->entity);

    $content = entityVisibleContentOf($owner, $assessment->refresh());

    expect($assessment->status->value)->toBe('needs_completion')
        ->and($content)->toContain('يرجى تحديث وصف السياسة المعتمدة.');
    assertNoNotesIn($content);
});

it('keeps the notes of a completed accredited request away from its result, feedback report and PDF', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview($version, $assessor);
    writeMarkerNotes($assessment, $version, $assessor, 'completed');
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
    $owner = ownerOf($assessment->entity);

    $content = entityVisibleContentOf($owner, $assessment->refresh());
    $url = (string) switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/feedback-report/export", ['locale' => 'ar'])->assertOk()->json('data.url');
    switchTo($owner)->get($url)->assertOk();
    $html = app(DocumentHtmlComposer::class)->compose(collect(app(FakePdfRenderer::class)->rendered())->last());

    expect($content)->toContain('وضوح السياسات المعتمدة');
    assertNoNotesIn($content);
    assertNoNotesIn($html);
    assertNoNotesIn(json_encode(collect(app(FakePdfRenderer::class)->rendered())->map->data->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
});

it('keeps the notes of an advanced request away from every entity response and its notifications', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    writeMarkerNotes($assessment, $version, $assessor, 'advanced');
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ACCREDITED_FULL_MARKS)])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()->assertJsonPath('data.status', 'awaiting_filtering');

    assertNoNotesIn(entityVisibleContentOf(ownerOf($assessment->entity), $assessment->refresh()));
});
