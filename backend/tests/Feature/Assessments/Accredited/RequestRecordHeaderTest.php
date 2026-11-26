<?php

declare(strict_types=1);

use App\Models\Certificates\Certificate;
use Illuminate\Support\Facades\Notification;

// The staff request record's header (request page v4.1): the entity's logo and whether the request has certificates.

beforeEach(function (): void {
    Notification::fake();
});

it('shows the entity logo on the request detail as a short-lived link to staff who may open it', function (): void {
    $assessment = assignedAccreditedRequest(publishedAccreditedCriteria(), assessorUser());
    uploadAndApproveLogo(ownerOf($assessment->entity), logoUpload(logoImageBytes()))->assertOk();

    $detail = switchTo(systemAdmin())->getJson("/api/v1/requests/{$assessment->id}")->assertOk();

    expect($detail->json('data.entity.logo.id'))->toBe($assessment->entity->refresh()->logo?->public_id)
        ->and($detail->json('data.entity.logo.url'))->toBeString();
    $this->get((string) $detail->json('data.entity.logo.url'))->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('leaves the logo out for a viewer who may not open the entity', function (): void {
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest(publishedAccreditedCriteria(), $assessor);
    uploadAndApproveLogo(ownerOf($assessment->entity), logoUpload(logoImageBytes()))->assertOk();

    switchTo($assessor)->getJson("/api/v1/requests/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.entity.logo', null)
        ->assertJsonPath('data.entity.id', $assessment->entity->id);
});

it('reports no logo when the entity has none', function (): void {
    $assessment = assignedAccreditedRequest(publishedAccreditedCriteria(), assessorUser());

    switchTo(systemAdmin())->getJson("/api/v1/requests/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.entity.logo', null);
});

it('tells whether the request has certificates, and keeps telling once it is reopened', function (): void {
    $version = publishedAccreditedCriteria();
    $submitted = submittedAccreditedRequest($version);
    switchTo(systemAdmin())->getJson("/api/v1/requests/{$submitted->id}")->assertOk()
        ->assertJsonPath('data.has_certificates', false);

    ['assessment' => $assessment] = certifiedAccreditedRequest($version);
    switchTo(systemAdmin())->getJson("/api/v1/requests/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.has_certificates', true);

    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk();

    expect(Certificate::query()->where('assessment_id', $assessment->id)->exists())->toBeTrue();
    switchTo(systemAdmin())->getJson("/api/v1/requests/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.status', fn (string $status): bool => $status !== 'completed')
        ->assertJsonPath('data.has_certificates', true);
});

it('refuses the request detail to its entity and answers not found for an unknown request', function (): void {
    $assessment = assignedAccreditedRequest(publishedAccreditedCriteria(), assessorUser());

    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/requests/{$assessment->id}")->assertForbidden();
    switchTo(systemAdmin())->getJson('/api/v1/requests/999999')->assertNotFound();
});
