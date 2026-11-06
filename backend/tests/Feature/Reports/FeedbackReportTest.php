<?php

declare(strict_types=1);

use App\Contracts\Documents\PdfRenderer;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Integrations\Documents\UnavailablePdfRenderer;
use App\Integrations\Fakes\FakePdfRenderer;
use App\Models\Audit\Audit;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('shows the entity a placeholder until the accredited result is completed', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = scoredAccreditedReview($version, assessorUser());

    $response = switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/feedback-report")->assertOk()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.status', 'under_review')
        ->assertJsonPath('data.number', $assessment->request_number);

    expect($response->json('data'))->not->toHaveKeys(['criteria', 'percent', 'entity']);
    switchTo(ownerOf($assessment->entity))->postJson("/api/v1/assessments/{$assessment->id}/feedback-report/export")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'report.not_available');
});

it('builds the report from the stored result, grouped by criterion, without any assessor identity', function (): void {
    ['assessment' => $assessment, 'certificate' => $certificate, 'assessor' => $assessor] = certifiedAccreditedRequest();
    $policyCode = 'M1.1';

    $response = switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/feedback-report")->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.percent', '77.00')
        ->assertJsonPath('data.total', '1155.00')
        ->assertJsonPath('data.entity.name.ar', $assessment->entity->name->ar)
        ->assertJsonPath('data.entity.type', $assessment->entity->entity_type->value)
        ->assertJsonPath('data.certificate.number', $certificate->number)
        ->assertJsonPath('data.certificate.level', 'silver')
        ->assertJsonPath('data.criteria.0.code', 'M1')
        ->assertJsonPath('data.criteria.0.subs.0.code', $policyCode)
        ->assertJsonPath('data.criteria.0.subs.0.strengths', 'وضوح السياسات المعتمدة وربطها بالأهداف الاستراتيجية.')
        ->assertJsonPath('data.criteria.1.subs.0.code', 'M2.1');

    expect($response->getContent())->not->toContain($assessor->name)
        ->and($response->getContent())->not->toContain((string) $assessor->email)
        ->and($response->json('data.criteria'))->toHaveCount(2);
});

it('lets the completing assessor and the global readers see the report and hides it from everyone else', function (): void {
    ['assessment' => $assessment, 'assessor' => $assessor] = certifiedAccreditedRequest();
    $url = "/api/v1/assessments/{$assessment->id}/feedback-report";

    switchTo($assessor)->getJson($url)->assertOk()->assertJsonPath('data.available', true);
    switchTo(systemAdmin())->getJson($url)->assertOk();
    switchTo(requestFollower())->getJson($url)->assertOk();
    switchTo(assessorUser())->getJson($url)->assertNotFound();
    switchTo(ownerOf(entityWithOwner()))->getJson($url)->assertNotFound();
    switchTo(staffUser(RoleKey::FinanceOfficer))->getJson($url)->assertForbidden();
    switchTo(staffUser(RoleKey::ContentOfficer))->getJson($url)->assertForbidden();
});

it('has no feedback report for a self assessment', function (): void {
    $version = publishedSelfCriteria();
    $assessment = scoredSelfDraft($version);

    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/feedback-report")->assertNotFound();
});

it('exports the report as a PDF rendered on demand through a signed link bound to the requester', function (): void {
    ['assessment' => $assessment] = certifiedAccreditedRequest();
    $owner = ownerOf($assessment->entity);

    $export = switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/feedback-report/export", ['locale' => 'en'])->assertOk()
        ->assertJsonPath('data.file_name', "feedback-report-{$assessment->request_number}-en.pdf")
        ->json('data');
    $rendered = count(app(FakePdfRenderer::class)->rendered());

    $download = $this->get($export['url'])->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private');

    $document = collect(app(FakePdfRenderer::class)->rendered())->last();
    expect($download->getContent())->toStartWith('%PDF-')
        ->and(count(app(FakePdfRenderer::class)->rendered()))->toBe($rendered + 1)
        ->and($document?->template)->toBe('feedback_report')
        ->and($document?->locale)->toBe('en')
        ->and($document?->data['percent'])->toBe('77.00')
        ->and($document?->watermark)->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::FeedbackReportExported->value)->sole()->new_values['locale'])->toBe('en');

    switchTo(systemAdmin())->get($export['url'])->assertForbidden()->assertJsonPath('code', 'signature.invalid');
    Date::setTestNow(Date::now()->addMinutes(6));
    switchTo($owner)->get($export['url'])->assertStatus(410)->assertJsonPath('code', 'signature.expired');
});

it('exports in the request language by default and refuses an unknown language', function (): void {
    ['assessment' => $assessment] = certifiedAccreditedRequest();
    $owner = ownerOf($assessment->entity);

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/feedback-report/export")->assertOk()
        ->assertJsonPath('data.file_name', "feedback-report-{$assessment->request_number}-ar.pdf");
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/feedback-report/export", ['locale' => 'fr'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('locale');
});

it('answers 503 for the report PDF while no renderer is installed', function (): void {
    ['assessment' => $assessment] = certifiedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    $url = (string) switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/feedback-report/export")->assertOk()->json('data.url');

    app()->instance(PdfRenderer::class, new UnavailablePdfRenderer);

    switchTo($owner)->get($url)->assertStatus(503)->assertJsonPath('code', 'document.renderer_unavailable');
    expect(Audit::query()->where('event', AuditEvent::FeedbackReportExported->value)->count())->toBe(0);
});
