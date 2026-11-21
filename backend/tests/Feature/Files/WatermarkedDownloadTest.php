<?php

declare(strict_types=1);

use App\Contracts\Watermark\OfficeConverter;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\RenditionStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Identity\RoleKey;
use App\Exceptions\Watermark\WatermarkFailed;
use App\Jobs\Files\ConvertOfficeFileJob;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Audit\Audit;
use App\Models\Files\FileAccessTrace;
use App\Models\Files\FileRendition;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use App\Support\ValueObjects\PageSize;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
});

function stampedEvidenceCase(): array
{
    $assessor = giveNationalId(assessorUser(), 9001);
    $assessment = assignedAccreditedRequest(publishedAccreditedCriteria(), $assessor);

    return [$assessor, $assessment];
}

function latestTraceCode(): string
{
    return FileAccessTrace::query()->latest('id')->value('trace_code');
}

it('stamps every page of a PDF with the viewer, the national ID and a trace code, and keeps the original', function (): void {
    [$assessor, $assessment] = stampedEvidenceCase();
    $original = multiPagePdf([new PageSize(595, 842), new PageSize(842, 595), new PageSize(595, 842)]);
    $evidence = replaceEvidenceContent($assessment, 'سياسة-الحوكمة.pdf', 'application/pdf', $original);

    $pdf = downloadEvidenceAs($assessor, $assessment, $evidence)->assertOk()->assertHeader('Content-Type', 'application/pdf')->streamedContent();

    $pages = array_slice(explode("\f", pdfTextOf($pdf)), 0, 3);
    $trace = latestTraceCode();
    expect(pdfPageCountOf($pdf))->toBe(3);
    foreach ($pages as $page) {
        expect($page)->toContain($trace)->toContain(fictionalNationalId(9001))->toContain('ORIGINAL');
    }
    expect(storedContentOf($evidence->storedFile))->toBe($original);
});

it('stamps a PDF that only restricts printing or copying, as uploads accept it', function (): void {
    [$assessor, $assessment] = stampedEvidenceCase();
    $evidence = replaceEvidenceContent($assessment, 'خطة-مقيدة.pdf', 'application/pdf', ownerRestrictedPdf());

    $pdf = downloadEvidenceAs($assessor, $assessment, $evidence)->assertOk()->assertHeader('Content-Type', 'application/pdf')->streamedContent();

    expect(pdfTextOf($pdf))->toContain(latestTraceCode())->toContain(fictionalNationalId(9001));
});

it('overlays JPEG and PNG images, keeping their size and format and leaving the original untouched', function (string $type, string $mime): void {
    [$assessor, $assessment] = stampedEvidenceCase();
    $original = solidImage($type);
    $evidence = replaceEvidenceContent($assessment, 'صورة-الشاهد.'.$type, $mime, $original);

    $stamped = downloadEvidenceAs($assessor, $assessment, $evidence)->assertOk()->assertHeader('Content-Type', $mime)->streamedContent();

    $info = getimagesizefromstring($stamped);
    expect($info[0] ?? null)->toBe(240)
        ->and($info[1] ?? null)->toBe(160)
        ->and($info['mime'] ?? null)->toBe($mime)
        ->and($stamped)->not->toBe($original)
        ->and(storedContentOf($evidence->storedFile))->toBe($original)
        ->and(FileAccessTrace::query()->count())->toBe(1);
})->with([
    'JPEG' => ['jpg', 'image/jpeg'],
    'PNG' => ['png', 'image/png'],
]);

it('converts Word, Excel and PowerPoint files once and serves a stamped PDF every time', function (string $fixture, string $mime): void {
    [$assessor, $assessment] = stampedEvidenceCase();
    $original = watermarkFixture($fixture);
    $evidence = replaceEvidenceContent($assessment, $fixture, $mime, $original);

    downloadEvidenceAs($assessor, $assessment, $evidence)->assertConflict()->assertJsonPath('code', 'file.preparing');
    expect(FileAccessTrace::query()->count())->toBe(0);

    $first = downloadEvidenceAs($assessor, $assessment, $evidence)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $firstTrace = latestTraceCode();
    $second = downloadEvidenceAs($assessor, $assessment, $evidence)->assertOk();

    expect($first->headers->get('Content-Disposition'))->toContain('.pdf')
        ->and(pdfTextOf($first->streamedContent()))->toContain($firstTrace)
        ->and(pdfTextOf($second->streamedContent()))->toContain(latestTraceCode())->not->toContain($firstTrace)
        ->and(FileRendition::query()->sole()->status)->toBe(RenditionStatus::Ready)
        ->and(storedContentOf($evidence->storedFile))->toBe($original);
})->with([
    'Word' => ['evidence-report.docx', DOCX_MIME],
    'Excel' => ['evidence-indicators.xlsx', XLSX_MIME],
    'PowerPoint' => ['evidence-slides.pptx', PPTX_MIME],
]);

it('prepares Office renditions ahead of time from the scheduled command', function (): void {
    [, $assessment] = stampedEvidenceCase();
    replaceEvidenceContent($assessment, 'evidence-report.docx', DOCX_MIME, watermarkFixture('evidence-report.docx'));

    Artisan::call('moashir:files:build-renditions');

    expect(FileRendition::query()->sole()->status)->toBe(RenditionStatus::Ready);
    Artisan::call('moashir:files:build-renditions');
    expect(FileRendition::query()->count())->toBe(1);
});

it('never serves an unstamped original when a file cannot be stamped', function (string $name, string $mime, Closure $contents, string $code): void {
    [$assessor, $assessment] = stampedEvidenceCase();
    $evidence = replaceEvidenceContent($assessment, $name, $mime, $contents());

    $response = downloadEvidenceAs($assessor, $assessment, $evidence)->assertUnprocessable()->assertJsonPath('code', $code);

    expect($response->headers->get('Content-Type'))->toContain('application/json')
        ->and(FileAccessTrace::query()->count())->toBe(0);
})->with([
    'password-protected PDF' => ['محمي.pdf', 'application/pdf', passwordProtectedPdf(...), 'file.watermark_failed'],
    'corrupt PDF' => ['تالف.pdf', 'application/pdf', fn (): string => "%PDF-1.7\nnot a document", 'file.watermark_failed'],
]);

it('serves nothing and remembers the failure when an Office file cannot be converted', function (): void {
    app()->bind(OfficeConverter::class, fn (): OfficeConverter => new class implements OfficeConverter
    {
        public function toPdf(string $source, string $outputDirectory, string $profileDirectory): string
        {
            throw WatermarkFailed::conversionFailed();
        }
    });
    [$assessor, $assessment] = stampedEvidenceCase();
    $evidence = replaceEvidenceContent($assessment, 'evidence-report.docx', DOCX_MIME, watermarkFixture('evidence-report.docx'));

    downloadEvidenceAs($assessor, $assessment, $evidence)->assertConflict()->assertJsonPath('code', 'file.preparing');
    downloadEvidenceAs($assessor, $assessment, $evidence)->assertUnprocessable()->assertJsonPath('code', 'file.conversion_failed');

    expect(FileRendition::query()->sole()->status)->toBe(RenditionStatus::Failed)
        ->and(FileAccessTrace::query()->count())->toBe(0);
});

it('records a trace and an audit row per view or download without the national ID', function (): void {
    Log::spy();
    [$assessor, $assessment] = stampedEvidenceCase();
    $evidence = replaceEvidenceContent($assessment, 'سياسة.pdf', 'application/pdf', multiPagePdf([new PageSize(595, 842)]));

    downloadEvidenceAs($assessor, $assessment, $evidence, 'view')->assertOk();
    downloadEvidenceAs($assessor, $assessment, $evidence)->assertOk();

    $traces = FileAccessTrace::query()->orderBy('id')->get();
    expect($traces->pluck('intent')->map->value->all())->toBe(['view', 'download'])
        ->and($traces->pluck('user_id')->unique()->all())->toBe([$assessor->id])
        ->and($traces[0]->trace_code)->toMatch('/^MSR-[0-9A-Z]{4}-[0-9A-Z]{4}-[0-9A-Z]{4}$/')
        ->and($traces[0]->trace_code)->not->toBe($traces[1]->trace_code);
    $audits = Audit::query()->where('event', AuditEvent::FileAccessed->value)->orderBy('id')->get();
    expect($audits->pluck('new_values.trace_code')->all())->toBe($traces->pluck('trace_code')->all())
        ->and(Audit::query()->get()->toJson())->not->toContain(fictionalNationalId(9001));
    Log::shouldNotHaveReceived('log', fn (...$arguments): bool => str_contains((string) json_encode($arguments), fictionalNationalId(9001)));
    Log::shouldNotHaveReceived('error', fn (...$arguments): bool => str_contains((string) json_encode($arguments), fictionalNationalId(9001)));
});

it('stamps the name and trace code only for a viewer who is not required to hold a national ID', function (): void {
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest(publishedAccreditedCriteria(), $assessor);
    $evidence = replaceEvidenceContent($assessment, 'سياسة.pdf', 'application/pdf', multiPagePdf([new PageSize(595, 842)]));

    $text = pdfTextOf(downloadEvidenceAs($assessor, $assessment, $evidence)->assertOk()->streamedContent());

    expect($text)->toContain(latestTraceCode())->not->toMatch('/\b[12]\d{9}\b/');
});

function evidenceLinkFor(User $viewer, Assessment $assessment, EvidenceFile $evidence): TestResponse
{
    return switchTo($viewer)->postJson("/api/v1/assessments/{$assessment->id}/evidence/{$evidence->public_id}/download");
}

function requiredViewerCase(): array
{
    requireNationalIdFor(RoleKey::Assessor);
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest(publishedAccreditedCriteria(), $assessor);
    $evidence = replaceEvidenceContent($assessment, 'سياسة.pdf', 'application/pdf', multiPagePdf([new PageSize(595, 842)]));

    return [$assessor, $assessment, $evidence];
}

it('refuses every file to a required viewer whose national ID is missing or pending, and audits the refusal', function (bool $pending, string $state): void {
    [$assessor, $assessment, $evidence] = requiredViewerCase();
    if ($pending) {
        switchTo($assessor)->postJson('/api/v1/me/national-id', ['national_id' => fictionalNationalId(9101)])->assertAccepted();
    }

    evidenceLinkFor($assessor, $assessment, $evidence)
        ->assertForbidden()
        ->assertJsonPath('code', 'national_id.required_for_files')
        ->assertJsonPath('meta.national_id_status', $state);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/evidence/bundles")
        ->assertForbidden()
        ->assertJsonPath('code', 'national_id.required_for_files');

    $refusal = Audit::query()->where('event', AuditEvent::FileAccessRefused->value)->latest('id')->firstOrFail();
    expect(FileAccessTrace::query()->count())->toBe(0)
        ->and(EvidenceBundle::query()->count())->toBe(0)
        ->and($refusal->new_values)->toMatchArray(['reason' => 'national_id_required', 'national_id_status' => $state])
        ->and(Audit::query()->get()->toJson())->not->toContain(fictionalNationalId(9101));
})->with(['missing' => [false, 'missing'], 'pending approval' => [true, 'pending']]);

it('opens files for a required viewer once the national ID is approved, stamped with it', function (): void {
    [$assessor, $assessment, $evidence] = requiredViewerCase();
    switchTo($assessor)->postJson('/api/v1/me/national-id', ['national_id' => fictionalNationalId(9102)])->assertAccepted();
    $request = IdentityChangeRequest::query()->sole();
    switchTo(systemAdmin())->postJson("/api/v1/identity-changes/{$request->public_id}/approve")->assertOk();

    $text = pdfTextOf(downloadEvidenceAs($assessor->refresh(), $assessment, $evidence)->assertOk()->streamedContent());

    expect($text)->toContain(latestTraceCode())->toContain(fictionalNationalId(9102));
});

it('opens files at once for the Super Admin who saved the own number directly', function (): void {
    requireNationalIdFor(RoleKey::Assessor);
    $superAdmin = superAdmin();
    $assessment = assignedAccreditedRequest(publishedAccreditedCriteria(), assessorUser());
    $evidence = replaceEvidenceContent($assessment, 'سياسة.pdf', 'application/pdf', multiPagePdf([new PageSize(595, 842)]));
    evidenceLinkFor($superAdmin, $assessment, $evidence)->assertForbidden();

    switchTo($superAdmin)->postJson('/api/v1/me/national-id', ['national_id' => fictionalNationalId(9103)])->assertCreated();

    expect(pdfTextOf(downloadEvidenceAs($superAdmin->refresh(), $assessment, $evidence)->assertOk()->streamedContent()))->toContain(fictionalNationalId(9103));
});

it('refuses a link issued before the account became required, and an earlier bundle', function (): void {
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest(publishedAccreditedCriteria(), $assessor);
    $evidence = replaceEvidenceContent($assessment, 'سياسة.pdf', 'application/pdf', multiPagePdf([new PageSize(595, 842)]));
    $url = (string) evidenceLinkFor($assessor, $assessment, $evidence)->assertOk()->json('data.url');
    $bundleId = switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/evidence/bundles")->assertCreated()->json('data.id');

    requireNationalIdFor(RoleKey::Assessor);

    test()->get($url)->assertForbidden()->assertJsonPath('code', 'national_id.required_for_files');
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/evidence/bundles/{$bundleId}/download")
        ->assertForbidden()
        ->assertJsonPath('code', 'national_id.required_for_files');
    expect(FileAccessTrace::query()->count())->toBe(1);
});

it('serves a bank transfer receipt as uploaded, unstamped, even to a required finance reviewer without a national ID', function (): void {
    requireNationalIdFor(RoleKey::FinanceOfficer);
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);
    $reviewer = transferReviewer();

    $url = (string) switchTo($reviewer)->postJson(bankTransferUrl($payment, 'proof/download'))->assertOk()->json('data.url');
    $received = switchTo($reviewer)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->streamedContent();

    expect($received)->toBe(storedContentOf($payment->bankTransfer->proofFile))
        ->and(FileAccessTrace::query()->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::FileAccessRefused->value)->count())->toBe(0);
});

it('stamps every file in an evidence bundle for its requester and keeps the bundle to that requester', function (): void {
    [$assessor, $assessment] = stampedEvidenceCase();
    replaceEvidenceContent($assessment, 'سياسة.pdf', 'application/pdf', multiPagePdf([new PageSize(595, 842)]));

    $bundleId = switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/evidence/bundles")->assertCreated()->json('data.id');
    $url = (string) switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/evidence/bundles/{$bundleId}/download")->assertOk()->json('data.url');
    $zipPath = (string) tempnam(sys_get_temp_dir(), 'moashir-test-zip-');
    file_put_contents($zipPath, test()->get($url)->assertOk()->streamedContent());
    $zip = new ZipArchive;
    $zip->open($zipPath);
    $entry = (string) $zip->getFromIndex(0);
    $zip->close();
    unlink($zipPath);

    expect(pdfTextOf($entry))->toContain(FileAccessTrace::query()->where('user_id', $assessor->id)->sole()->trace_code)
        ->and(pdfTextOf($entry))->toContain(fictionalNationalId(9001));
    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/evidence/bundles/{$bundleId}/download")->assertNotFound();
});

it('stamps assessment evidence only, never transfer proofs, refund evidence or VAT certificates', function (): void {
    expect(StoredFilePurpose::watermarked())->toBe([StoredFilePurpose::AssessmentEvidence])
        ->and(StoredFilePurpose::TransferProof->isStampedForViewer())->toBeFalse()
        ->and(StoredFilePurpose::RefundEvidence->isStampedForViewer())->toBeFalse()
        ->and(StoredFilePurpose::VatCertificate->isStampedForViewer())->toBeFalse()
        ->and(StoredFilePurpose::EvidenceBundle->isStampedForViewer())->toBeTrue();
});

it('finds who received a stamped copy from its trace code', function (): void {
    [$assessor, $assessment] = stampedEvidenceCase();
    $evidence = replaceEvidenceContent($assessment, 'سياسة.pdf', 'application/pdf', multiPagePdf([new PageSize(595, 842)]));
    downloadEvidenceAs($assessor, $assessment, $evidence)->assertOk();

    $this->artisan('moashir:files:trace', ['code' => strtolower(latestTraceCode())])
        ->expectsOutputToContain('user #'.$assessor->id)
        ->assertSuccessful();
    $this->artisan('moashir:files:trace', ['code' => 'MSR-0000-0000-0000'])->assertFailed();
});

it('refuses to stamp a file above the size limit', function (): void {
    config(['moashir.watermark.max_source_bytes' => 1024]);
    [$assessor, $assessment] = stampedEvidenceCase();
    $evidence = replaceEvidenceContent($assessment, 'كبير.pdf', 'application/pdf', multiPagePdf([new PageSize(595, 842)], str_repeat('LARGE ', 400)));

    downloadEvidenceAs($assessor, $assessment, $evidence)->assertUnprocessable()->assertJsonPath('code', 'file.too_large_to_stamp');
    expect(FileAccessTrace::query()->count())->toBe(0);
});

it('never starts LibreOffice while serving a download', function (): void {
    [$assessor, $assessment] = stampedEvidenceCase();
    $evidence = replaceEvidenceContent($assessment, 'evidence-report.docx', DOCX_MIME, watermarkFixture('evidence-report.docx'));
    Queue::fake();

    downloadEvidenceAs($assessor, $assessment, $evidence)->assertConflict()->assertJsonPath('code', 'file.preparing');

    Queue::assertPushed(ConvertOfficeFileJob::class, fn (ConvertOfficeFileJob $job): bool => $job->storedFileId === $evidence->stored_file_id);
    expect(FileRendition::query()->count())->toBe(0);
});
