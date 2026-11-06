<?php

declare(strict_types=1);

use App\Contracts\Documents\PdfRenderer;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Integrations\Documents\UnavailablePdfRenderer;
use App\Models\Audit\Audit;
use App\Models\Certificates\Certificate;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('lists and shows the own certificates to the entity without the assessor identity', function (): void {
    ['assessment' => $assessment, 'certificate' => $certificate] = certifiedAccreditedRequest();
    $owner = ownerOf($assessment->entity);

    $response = switchTo($owner)->getJson('/api/v1/certificates')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $certificate->public_id)
        ->assertJsonPath('data.0.number', $certificate->number)
        ->assertJsonPath('data.0.level', 'silver')
        ->assertJsonPath('data.0.level_label.ar', 'المستوى الفضي')
        ->assertJsonPath('data.0.percent', '77.00')
        ->assertJsonPath('data.0.request.path', 'accredited')
        ->assertJsonPath('data.0.status', 'issued');

    expect($response->json('data.0'))->not->toHaveKeys(['issued_by', 'revoked_by', 'reissue_reason'])
        ->and($response->json('data.0.verification_code'))->toMatch('/^([0-9A-Z]{4}-){6}[0-9A-Z]{4}$/');

    switchTo($owner)->getJson("/api/v1/certificates/{$certificate->public_id}")->assertOk()->assertJsonPath('data.number', $certificate->number);
});

it('hides other entities certificates', function (): void {
    ['certificate' => $certificate] = certifiedAccreditedRequest();
    $stranger = ownerOf(entityWithOwner());

    switchTo($stranger)->getJson('/api/v1/certificates')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    switchTo($stranger)->getJson("/api/v1/certificates/{$certificate->public_id}")->assertNotFound();
    switchTo($stranger)->postJson("/api/v1/certificates/{$certificate->public_id}/download")->assertNotFound();
});

it('shows every accredited certificate with the issuer to the System Administrator only', function (): void {
    ['certificate' => $certificate, 'assessor' => $assessor] = certifiedAccreditedRequest();

    switchTo(systemAdmin())->getJson('/api/v1/certificates?filter[status]=issued&filter[level]=silver')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.issued_by.id', $assessor->id);
    switchTo(systemAdmin())->getJson('/api/v1/certificates?search='.strtolower($certificate->number))->assertOk()->assertJsonPath('meta.pagination.total', 1);
    switchTo(systemAdmin())->getJson('/api/v1/certificates?filter[level]=gold')->assertOk()->assertJsonPath('meta.pagination.total', 0);

    switchTo($assessor)->getJson('/api/v1/certificates')->assertForbidden();
    switchTo(requestFollower())->getJson('/api/v1/certificates')->assertForbidden();
    switchTo(staffUser(RoleKey::AwardSecretary))->getJson('/api/v1/certificates')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    switchTo(staffUser(RoleKey::AwardSecretary))->getJson("/api/v1/certificates/{$certificate->public_id}")->assertNotFound();
});

it('validates the certificate filters', function (string $query): void {
    certifiedAccreditedRequest();

    switchTo(systemAdmin())->getJson('/api/v1/certificates?'.$query)->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
})->with(['filter[status]=lost', 'filter[level]=platinum', 'per_page=500', 'filter[unknown]=1']);

it('downloads the stored certificate PDF through a short-lived signed link and audits the download', function (): void {
    ['assessment' => $assessment, 'certificate' => $certificate] = certifiedAccreditedRequest();
    $owner = ownerOf($assessment->entity);

    $url = (string) switchTo($owner)->postJson("/api/v1/certificates/{$certificate->public_id}/download")->assertOk()
        ->assertJsonPath('data.file_name', $certificate->number.'.pdf')
        ->json('data.url');
    $download = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($download->streamedContent())->toStartWith('%PDF-')
        ->and(Audit::query()->where('event', AuditEvent::FileDownloaded->value)->where('new_values->attachable_type', (new Certificate)->getMorphClass())->count())->toBe(1);

    switchTo(systemAdmin())->get($url)->assertForbidden()->assertJsonPath('code', 'signature.invalid');
});

it('renders a missing certificate PDF on first download and answers 503 while no renderer is installed', function (): void {
    $version = publishedAccreditedCriteria();
    ['assessment' => $assessment, 'certificate' => $certificate] = certifiedAccreditedRequest($version);
    expect(Certificate::query()->whereKey($certificate->id)->sole()->pdf_file_id)->not->toBeNull();

    app()->instance(PdfRenderer::class, new UnavailablePdfRenderer);
    $other = certifiedAccreditedRequest($version)['certificate'];
    expect($other->refresh()->pdf_file_id)->toBeNull();

    switchTo(ownerOf($other->assessment->entity))->postJson("/api/v1/certificates/{$other->public_id}/download")
        ->assertStatus(503)
        ->assertJsonPath('code', 'document.renderer_unavailable');
    switchTo(ownerOf($assessment->entity))->postJson("/api/v1/certificates/{$certificate->public_id}/download")->assertOk();
});
