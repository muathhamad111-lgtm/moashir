<?php

declare(strict_types=1);

use App\Contracts\Documents\PdfRenderer;
use App\Enums\Audit\AuditEvent;
use App\Integrations\Documents\UnavailablePdfRenderer;
use App\Integrations\Fakes\FakePdfRenderer;
use App\Models\Audit\Audit;
use App\Services\Documents\DocumentHtmlComposer;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

const BOARD_MARKS = ['M1.1' => '85.00', 'M1.2' => '45.00', 'M2.1' => '75.00', 'M2.2' => '90.00'];

beforeEach(function (): void {
    Notification::fake();
});

it('exports the board report through a signed link bound to the requester and audits every download', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), BOARD_MARKS);
    $owner = ownerOf($assessment->entity);

    $export = switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/board-report/export", ['locale' => 'en'])->assertOk()
        ->assertJsonPath('data.file_name', "board-report-{$assessment->request_number}-en.pdf")
        ->json('data');

    $download = $this->get($export['url'])->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private');
    $document = collect(app(FakePdfRenderer::class)->rendered())->last();
    $audit = Audit::query()->where('event', AuditEvent::BoardReportExported->value)->sole();

    expect($download->getContent())->toStartWith('%PDF-')
        ->and($document?->template)->toBe('board_report')
        ->and($document?->locale)->toBe('en')
        ->and($document?->data['score']['percent'])->toBe('75.75')
        ->and($document?->data['bars'])->toHaveCount(2)
        ->and($audit->new_values)->toMatchArray([
            'assessment_id' => $assessment->id,
            'snapshot_version' => 1,
            'locale' => 'en',
            'gap_thresholds_version' => 1,
            'maturity_bands_version' => 1,
        ])
        ->and($audit->tags)->toContain('download')
        ->and($audit->user_id)->toBe($owner->id)
        ->and($audit->ip_address)->not->toBeNull();

    switchTo(systemAdmin())->get($export['url'])->assertForbidden()->assertJsonPath('code', 'signature.invalid');
    switchTo($owner)->get($export['url'].'x')->assertForbidden()->assertJsonPath('code', 'signature.invalid');
    Date::setTestNow(Date::now()->addMinutes(6));
    switchTo($owner)->get($export['url'])->assertStatus(410)->assertJsonPath('code', 'signature.expired');
});

it('exports in the request language by default and refuses an unknown language', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), BOARD_MARKS);
    $owner = ownerOf($assessment->entity);

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/board-report/export")->assertOk()
        ->assertJsonPath('data.file_name', "board-report-{$assessment->request_number}-ar.pdf");
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/board-report/export", ['locale' => 'fr'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('locale');
});

it('throttles the board report exports', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), BOARD_MARKS);
    $owner = ownerOf($assessment->entity);

    foreach (range(1, 5) as $attempt) {
        switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/board-report/export")->assertOk();
    }

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/board-report/export")->assertTooManyRequests();
});

it('denies the board report to other entities and to roles without the insights permissions', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), BOARD_MARKS);
    $url = "/api/v1/assessments/{$assessment->id}/board-report/export";

    switchTo(ownerOf(entityWithOwner()))->postJson($url)->assertNotFound();
    switchTo(assessorUser())->postJson($url)->assertForbidden();
    switchTo(systemAdmin())->postJson($url)->assertOk();
});

it('answers 503 while no renderer is installed and writes no audit row', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), BOARD_MARKS);
    $owner = ownerOf($assessment->entity);
    $url = (string) switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/board-report/export")->json('data.url');

    app()->instance(PdfRenderer::class, new UnavailablePdfRenderer);

    switchTo($owner)->get($url)->assertStatus(503)->assertJsonPath('code', 'document.renderer_unavailable');
    expect(Audit::query()->where('event', AuditEvent::BoardReportExported->value)->count())->toBe(0);
});

it('renders the one-page document in both languages with the truncated assessor summary and no assessor identity', function (string $locale, string $title, string $pointer): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    accreditedResultFor($entity, $version, ['M1.1' => '70.00', 'M1.2' => '70.00', 'M2.1' => '70.00', 'M2.2' => '70.00'], '2026-01-01 09:00:00');
    $assessment = accreditedResultFor($entity, $version, BOARD_MARKS, '2026-06-01 09:00:00');
    $assessor = assessorUser();
    writeFeedbackFor($assessment, ['M2.2' => ['strength' => str_repeat('نص قوة طويل ', 40)]], $assessor);
    writeAssessorSummary($assessment, str_repeat('ملخص المقيم للجهة ', 120));
    $owner = ownerOf($entity);
    $url = (string) switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/board-report/export", ['locale' => $locale])->json('data.url');

    switchTo($owner)->get($url)->assertOk();
    $document = collect(app(FakePdfRenderer::class)->rendered())->last();
    $html = app(DocumentHtmlComposer::class)->compose($document);

    expect(mb_strlen((string) $document?->data['assessor_summary_excerpt']['text']))->toBeLessThanOrEqual(600)
        ->and($document?->data['assessor_summary_excerpt']['truncated'])->toBeTrue()
        ->and(mb_strlen((string) $document?->data['strong_areas'][0]['quote']))->toBeLessThanOrEqual(201)
        ->and($html)->toContain($title)
        ->and($html)->toContain($pointer)
        ->and($html)->toContain('5.75')
        ->and($html)->not->toContain($assessor->name)
        ->and($html)->not->toContain('<svg class="qr');
})->with([
    'arabic' => ['ar', 'تقرير مجلس الإدارة', 'النص الكامل في التقرير التعقيبي'],
    'english' => ['en', 'Board report', 'full text in the feedback report'],
]);
