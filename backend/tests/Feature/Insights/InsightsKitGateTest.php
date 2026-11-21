<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('keeps the excellence tools locked for the entity until the Kit is activated for the result', function (): void {
    ['assessment' => $assessment] = certifiedAccreditedRequest();
    $owner = ownerOf($assessment->entity);

    foreach (['/api/v1/entities/me/insights', '/api/v1/entities/me/insights/results', '/api/v1/entities/me/report-library'] as $url) {
        switchTo($owner)->getJson($url)->assertForbidden()->assertJsonPath('code', 'insights.kit_required');
    }

    foreach (['strengths-gaps', 'maturity', 'executive-summary'] as $tool) {
        switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/insights/{$tool}")
            ->assertForbidden()
            ->assertJsonPath('code', 'insights.kit_required');
    }

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/board-report/export")
        ->assertForbidden()
        ->assertJsonPath('code', 'insights.kit_required');
});

it('opens the tools for the entity once the Kit is active', function (): void {
    ['assessment' => $assessment] = certifiedAccreditedRequest();
    activateInsightsKit();

    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/insights/strengths-gaps")->assertOk();
});

it('answers not found to another entity before any Kit check and leaves staff read-only access unchanged', function (): void {
    ['assessment' => $assessment] = certifiedAccreditedRequest();

    switchTo(ownerOf(entityWithOwner()))->getJson("/api/v1/assessments/{$assessment->id}/insights/maturity")->assertNotFound();
    switchTo(systemAdmin())->getJson("/api/v1/assessments/{$assessment->id}/insights/maturity")->assertOk();
});
