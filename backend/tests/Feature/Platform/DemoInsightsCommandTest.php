<?php

declare(strict_types=1);

use App\Actions\Insights\SaveMaturityText;
use App\Data\Demo\DemoPeople;
use App\Data\Demo\DemoUserAccount;
use App\Data\Insights\MaturityTextEdit;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\ResultSnapshotKind;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Enums\Certificates\CertificateStatus;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Identity\RoleKey;
use App\Enums\Insights\MaturityBand;
use App\Enums\Platform\DemoRecordKind;
use App\Enums\Settings\SettingKey;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentSummary;
use App\Models\Certificates\Certificate;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Insights\MaturityInterpretation;
use App\Models\Platform\DemoRecord;
use App\Services\Assessments\RequestNumberAllocator;
use App\Services\Demo\DemoBankTransferInstaller;
use App\Services\Demo\DemoCycleInstaller;
use App\Services\Demo\DemoInsightsCastResolver;
use App\Services\Demo\DemoPaymentInstaller;
use App\Services\Demo\DemoPeopleInstaller;
use App\Services\Demo\DemoPriceInstaller;
use App\Services\Demo\DemoResultScorer;
use App\Services\Settings\SettingsStore;
use App\Support\ValueObjects\LocalizedText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

const CONTENT_OFFICER_TEXT = ['ar' => 'نص كتبه مسؤول المحتوى ويجب أن يبقى كما هو.', 'en' => 'Text written by the Content Officer that must stay as it is.'];

function installDemoWithoutInsights(): DemoPeople
{
    $people = DB::transaction(static function (): DemoPeople {
        $people = app(DemoPeopleInstaller::class)->install();
        $systemAdmin = $people->staffMember(RoleKey::SystemAdmin);
        app(DemoPriceInstaller::class)->install($systemAdmin);
        app(DemoCycleInstaller::class)->install($people->staffMember(RoleKey::AwardSecretary));
        [$paidOwner, $pendingOwner, $settledOwner] = $people->entityOwners;
        app(DemoPaymentInstaller::class)->installPaid($paidOwner->user);
        app(DemoBankTransferInstaller::class)->install($systemAdmin, $people->staffMember(RoleKey::FinanceOfficer), $pendingOwner, $settledOwner);

        return $people;
    });
    enablePaymentChannels(PaymentChannel::BankTransfer);

    return $people;
}

/**
 * @return list<Entity>
 */
function demoEntitiesInInstallOrder(): array
{
    return array_values(Entity::query()->whereIn('id', DemoRecord::query()->where('kind', DemoRecordKind::Entity)->select('record_id'))->orderBy('id')->get()->all());
}

function existingSelfResultOf(Entity $entity): Assessment
{
    $version = CriteriaVersion::query()->published()->firstOrFail();
    $assessment = new Assessment;
    $assessment->forceFill([
        'request_number' => app(RequestNumberAllocator::class)->next(AssessmentPath::SelfAssessment),
        'entity_id' => $entity->id,
        'path' => AssessmentPath::SelfAssessment,
        'status' => SelfAssessmentStatus::Submitted,
        'criteria_version_id' => $version->id,
        'scale_kind' => $version->scale_kind,
        'submitted_at' => CarbonImmutable::parse('2026-10-20 09:00:00'),
    ])->save();
    app(DemoResultScorer::class)->score($assessment, $version, [72, 64, 70, 58], ResultSnapshotKind::SelfResult, null);
    $assessment->forceFill(['status' => SelfAssessmentStatus::ResultAvailable, 'completed_at' => CarbonImmutable::parse('2026-10-20 09:00:00')])->save();

    return $assessment->refresh();
}

/**
 * @return array{exit: int, output: string}
 */
function runInsightsOnly(array $options = []): array
{
    $exit = Artisan::call('moashir:demo:install', ['--insights-only' => true, ...$options]);

    return ['exit' => $exit, 'output' => Artisan::output()];
}

/**
 * @return array<string, int>
 */
function demoDataCounts(): array
{
    return [
        'assessments' => Assessment::query()->count(),
        'payments' => Payment::query()->count(),
        'receipts' => PaymentReceipt::query()->count(),
        'certificates' => Certificate::query()->count(),
        'summaries' => AssessmentSummary::query()->count(),
        'demo_records' => DemoRecord::query()->count(),
        'audits' => DB::table('audits')->count(),
        'texts' => MaturityInterpretation::query()->whereNotNull('text')->count(),
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function demoRowsOf(string $model, string $column, int $id): array
{
    return $model::query()->where($column, $id)->orderBy('id')->get()->mapWithKeys(static fn ($row): array => [$row->id => $row->getAttributes()])->all();
}

it('adds every excellence-tools case to a demo installed before them', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    installDemoWithoutInsights();
    [$gold, $selfOnly, $mixed] = demoEntitiesInInstallOrder();
    Notification::fake();

    $result = runInsightsOnly();

    expect($result['exit'])->toBe(0)
        ->and($result['output'])->toContain('Demo excellence-tools cases installed: accredited results: 4, revoked certificates: 1, assessor summaries: 1, self results: 1, maturity texts filled: 4.');

    $completed = Assessment::query()->where('path', AssessmentPath::Accredited)->where('status', AccreditedAssessmentStatus::Completed)->with('payment')->get();
    $certificates = Certificate::query()->whereIn('assessment_id', $completed->modelKeys())->orderBy('id')->get();

    expect($completed->where('entity_id', $gold->id))->toHaveCount(2)
        ->and($completed->where('entity_id', $mixed->id))->toHaveCount(2)
        ->and($completed->every(static fn (Assessment $assessment): bool => $assessment->payment?->isTest() === true))->toBeTrue()
        ->and($certificates->where('entity_id', $gold->id)->pluck('status')->all())->toBe([CertificateStatus::Issued, CertificateStatus::Issued])
        ->and($certificates->where('entity_id', $gold->id)->map(static fn (Certificate $certificate): ?string => $certificate->level?->value)->values()->all())->toBe(['silver', 'gold'])
        ->and($certificates->where('entity_id', $mixed->id)->pluck('status')->all())->toBe([CertificateStatus::Issued, CertificateStatus::Revoked])
        ->and($certificates->every(static fn (Certificate $certificate): bool => $certificate->is_test && str_starts_with($certificate->number, 'CRT-')))->toBeTrue()
        ->and(PaymentReceipt::query()->whereIn('payment_id', $completed->pluck('payment_id'))->whereNull('number')->where('is_test', true)->count())->toBe(4)
        ->and(AssessmentSummary::query()->count())->toBe(1)
        ->and(Assessment::query()->where('entity_id', $selfOnly->id)->where('path', AssessmentPath::SelfAssessment)->where('status', SelfAssessmentStatus::ResultAvailable)->count())->toBe(1)
        ->and(MaturityInterpretation::query()->whereNull('main_criterion_code')->whereNotNull('text')->count())->toBe(4)
        ->and(DemoRecord::query()->where('kind', DemoRecordKind::InsightsAssessment)->count())->toBe(5)
        ->and(app(SettingsStore::class)->get(SettingKey::PaymentChannels))->toBe(['bank_transfer']);
    Notification::assertNothingSent();

    $overview = switchTo(ownerOf($gold))->getJson('/api/v1/entities/me/insights')->assertOk()
        ->assertJsonPath('data.empty_state', null)
        ->assertJsonPath('data.selected.certificate.level', 'gold')
        ->json('data');
    switchTo(ownerOf($gold))->getJson("/api/v1/assessments/{$overview['selected']['assessment_id']}/insights/executive-summary")->assertOk()
        ->assertJsonPath('data.change.previous.certificate.level', 'silver')
        ->assertJsonPath('data.assessor_summary.version', 1);
    expect(switchTo(ownerOf($gold))->getJson("/api/v1/assessments/{$overview['selected']['assessment_id']}/insights/executive-summary")->json('data.change.delta_pp'))->not->toBeNull();
    switchTo(ownerOf($selfOnly))->getJson('/api/v1/entities/me/insights')->assertOk()->assertJsonPath('data.empty_state.reason', 'only_self');
    switchTo(ownerOf($mixed))->getJson('/api/v1/entities/me/insights')->assertOk()
        ->assertJsonPath('data.selected.certificate.status', 'issued')
        ->assertJsonPath('data.exclusions.0.reason', 'certificate_revoked');
});

it('writes the same audit events as the fresh install and keeps the revocation reason on its own row', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    installDemoWithoutInsights();
    $lastAuditId = (int) DB::table('audits')->max('id');

    expect(runInsightsOnly()['exit'])->toBe(0);

    $events = DB::table('audits')->where('id', '>', $lastAuditId)->whereNot('event', 'created')->whereNot('event', 'updated')
        ->select('event')->selectRaw('count(*) as total')->groupBy('event')->pluck('total', 'event')->all();
    ksort($events);

    expect($events)->toBe([
        'assessment.created' => 4,
        'certificate.issued' => 4,
        'certificate.revoked' => 1,
        'content.maturity_texts_updated' => 4,
        'payment.bound' => 4,
        'payment.created' => 4,
        'payment.paid' => 4,
        'receipt.issued' => 4,
        'settings.periodic_updated' => 1,
    ])->and(DB::table('audits')->where('id', '>', $lastAuditId)->whereNotNull('reason')->pluck('event')->all())->toBe(['certificate.revoked']);
});

it('leaves existing drafts, self results, payments and receipts of the demo entities untouched', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    installDemoWithoutInsights();
    [$gold, $selfOnly, $mixed] = demoEntitiesInInstallOrder();
    existingSelfResultOf($gold);
    existingSelfResultOf($selfOnly);
    $openDrafts = Assessment::query()->where('path', AssessmentPath::Accredited)->where('status', AccreditedAssessmentStatus::Draft)->pluck('entity_id')->all();
    $before = [];

    foreach ([$gold, $selfOnly, $mixed] as $entity) {
        $before[$entity->id] = [
            'assessments' => demoRowsOf(Assessment::class, 'entity_id', $entity->id),
            'payments' => demoRowsOf(Payment::class, 'entity_id', $entity->id),
            'entity' => $entity->refresh()->getAttributes(),
        ];
    }

    $receiptsBefore = PaymentReceipt::query()->orderBy('id')->get()->map(static fn (PaymentReceipt $receipt): array => $receipt->getAttributes())->all();

    expect($openDrafts)->toEqualCanonicalizing([$gold->id, $mixed->id])
        ->and(runInsightsOnly()['exit'])->toBe(0);

    foreach ([$gold, $selfOnly, $mixed] as $entity) {
        $assessmentsAfter = demoRowsOf(Assessment::class, 'entity_id', $entity->id);
        $paymentsAfter = demoRowsOf(Payment::class, 'entity_id', $entity->id);

        expect(array_intersect_key($assessmentsAfter, $before[$entity->id]['assessments']))->toBe($before[$entity->id]['assessments'])
            ->and(array_intersect_key($paymentsAfter, $before[$entity->id]['payments']))->toBe($before[$entity->id]['payments'])
            ->and($entity->refresh()->getAttributes())->toBe($before[$entity->id]['entity']);
    }

    expect(array_slice(PaymentReceipt::query()->orderBy('id')->get()->map(static fn (PaymentReceipt $receipt): array => $receipt->getAttributes())->all(), 0, count($receiptsBefore)))->toBe($receiptsBefore)
        ->and(Assessment::query()->where('path', AssessmentPath::Accredited)->where('status', AccreditedAssessmentStatus::Draft)->pluck('entity_id')->all())->toEqualCanonicalizing($openDrafts);
    switchTo(ownerOf($gold))->getJson('/api/v1/entities/me/insights')->assertOk()
        ->assertJsonPath('data.selected.certificate.level', 'gold')
        ->assertJsonPath('data.in_progress_request.assessment_id', Assessment::query()->where('entity_id', $gold->id)->where('status', AccreditedAssessmentStatus::Draft)->value('id'));
});

it('never changes an entity that the demo did not create', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    installDemoWithoutInsights();
    $foreign = Entity::factory()->withOwner()->create();
    $foreignSelfResult = existingSelfResultOf($foreign);
    $foreignBefore = $foreign->refresh()->getAttributes();
    $foreignAssessments = demoRowsOf(Assessment::class, 'entity_id', $foreign->id);

    expect(runInsightsOnly()['exit'])->toBe(0)
        ->and($foreign->refresh()->getAttributes())->toBe($foreignBefore)
        ->and(demoRowsOf(Assessment::class, 'entity_id', $foreign->id))->toBe($foreignAssessments)
        ->and(Payment::query()->where('entity_id', $foreign->id)->exists())->toBeFalse()
        ->and(Certificate::query()->where('entity_id', $foreign->id)->exists())->toBeFalse()
        ->and(DemoRecord::query()->where('kind', DemoRecordKind::InsightsAssessment)->where('record_id', $foreignSelfResult->id)->exists())->toBeFalse();
});

it('fills only the empty generic maturity texts and keeps what the Content Officer wrote', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    $people = installDemoWithoutInsights();
    $contentOfficer = $people->staffMember(RoleKey::ContentOfficer);
    $mainCode = (string) CriteriaVersion::query()->published()->firstOrFail()->mainCriteria()->orderBy('id')->value('code');
    $written = new LocalizedText(CONTENT_OFFICER_TEXT['ar'], CONTENT_OFFICER_TEXT['en']);
    DB::transaction(static function () use ($contentOfficer, $written, $mainCode): void {
        app(SaveMaturityText::class)->handle($contentOfficer, new MaturityTextEdit(MaturityBand::Mature, null, $written));
        app(SaveMaturityText::class)->handle($contentOfficer, new MaturityTextEdit(MaturityBand::Leading, $mainCode, $written));
    });

    $result = runInsightsOnly();

    expect($result['exit'])->toBe(0)
        ->and($result['output'])->toContain('maturity texts filled: 3')
        ->and(MaturityInterpretation::query()->whereNull('main_criterion_code')->where('band', MaturityBand::Mature)->sole()->text?->toArray())->toBe(CONTENT_OFFICER_TEXT)
        ->and(MaturityInterpretation::query()->where('main_criterion_code', $mainCode)->where('band', MaturityBand::Leading)->sole()->text?->toArray())->toBe(CONTENT_OFFICER_TEXT)
        ->and(MaturityInterpretation::query()->whereNull('main_criterion_code')->whereNotNull('text')->count())->toBe(4);
});

it('refuses a second run and writes nothing', function (): void {
    Log::spy();
    enableDemoData();
    installDemoPrerequisites();
    installDemoWithoutInsights();
    runInsightsOnly();
    $counts = demoDataCounts();

    $result = runInsightsOnly();

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain('The excellence-tools demo cases are already installed; nothing was changed.')
        ->and(demoDataCounts())->toBe($counts);
    expectRefusalWasLogged('insights_already_installed');
});

it('refuses the insights after a fresh install that already added them', function (): void {
    Log::spy();
    enableDemoData();
    installDemoPrerequisites();
    runDemoCommand('moashir:demo:install', demoAccountsDirectory().'/accounts.txt');
    $counts = demoDataCounts();

    expect(runInsightsOnly()['exit'])->toBe(1)
        ->and(DemoRecord::query()->where('kind', DemoRecordKind::InsightsAssessment)->count())->toBe(5)
        ->and(demoDataCounts())->toBe($counts);
    expectRefusalWasLogged('insights_already_installed');
});

it('refuses the insights without the flag, without an installed demo or outside the demo posture', function (Closure $arrange, string $reason, string $message): void {
    Log::spy();
    installDemoPrerequisites();
    $arrange();
    $counts = demoDataCounts();

    $result = runInsightsOnly();

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain($message)
        ->and(demoDataCounts())->toBe($counts);
    expectRefusalWasLogged($reason);
})->with([
    'flag not set' => [static function (): void {}, 'flag_not_set', 'APP_DEMO_DATA is not set to true'],
    'demo not installed' => [static function (): void {
        enableDemoData();
    }, 'not_installed', 'No demo accounts exist'],
    'posture not demo' => [static function (): void {
        enableDemoData();
        installDemoWithoutInsights();
        app()['env'] = 'staging';
    }, 'posture_not_demo', 'MOASHIR_DEMO is not true'],
]);

it('refuses the insights option combined with another demo option', function (Closure $options): void {
    enableDemoData();
    installDemoPrerequisites();
    installDemoWithoutInsights();
    $counts = demoDataCounts();
    $given = $options();

    $result = runInsightsOnly($given);

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain('--insights-only cannot be combined with --content-only or --accounts-file')
        ->and(demoDataCounts())->toBe($counts);

    if (isset($given['--accounts-file'])) {
        expect(file_exists($given['--accounts-file']))->toBeFalse();
    }
})->with([
    'content only' => [static fn (): array => ['--content-only' => true]],
    'accounts file' => [static fn (): array => ['--accounts-file' => demoAccountsDirectory().'/accounts.txt']],
]);

it('refuses clearly and writes nothing when a demo entity has an open accredited payment order', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    installDemoWithoutInsights();
    $mixed = demoEntitiesInInstallOrder()[2];
    Payment::factory()->forEntity($mixed)->testMode()->create(['initiated_by_user_id' => ownerOf($mixed)->id]);
    $counts = demoDataCounts();

    $result = runInsightsOnly();

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain('a demo entity has an open accredited payment order')
        ->and(demoDataCounts())->toBe($counts);
});

it('resolves the demo cast from the demo records in install order', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    $people = installDemoWithoutInsights();

    $cast = app(DemoInsightsCastResolver::class)->resolve();

    expect([$cast->goldOwner->id, $cast->selfOnlyOwner->id, $cast->mixedOwner->id])->toBe(array_map(static fn (DemoUserAccount $owner): int => $owner->user->id, $people->entityOwners))
        ->and($cast->assessor->id)->toBe($people->staffMember(RoleKey::Assessor)->id)
        ->and($cast->systemAdmin->id)->toBe($people->staffMember(RoleKey::SystemAdmin)->id)
        ->and($cast->contentOfficer->id)->toBe($people->staffMember(RoleKey::ContentOfficer)->id);
});

it('refuses with a missing prerequisite when a demo entity was deleted', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    installDemoWithoutInsights();
    $counts = demoDataCounts();
    demoEntitiesInInstallOrder()[1]->delete();

    $result = runInsightsOnly();

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain('the three demo entities and their owner accounts are not all present')
        ->and(demoDataCounts())->toBe($counts);
});

it('fills real accredited periods and certificate validity for the demo results', function (): void {
    enableDemoData();
    installDemoPrerequisites();
    installDemoWithoutInsights();

    expect(runInsightsOnly()['exit'])->toBe(0);

    $results = Assessment::query()
        ->with(['period', 'certificate'])
        ->where('path', AssessmentPath::Accredited)
        ->whereIn('id', DemoRecord::query()->where('kind', DemoRecordKind::InsightsAssessment)->select('record_id'))
        ->orderBy('submitted_at')
        ->get();

    expect($results->map(static fn (Assessment $assessment): ?string => $assessment->period?->key)->all())->toBe(['2025-Q4', '2026-Q1', '2026-Q3', '2026-Q3'])
        ->and($results->map(static fn (Assessment $assessment): ?string => $assessment->certificate?->valid_until?->toDateString())->all())->toBe(['2026-03-31', '2026-06-30', '2026-12-31', '2026-12-31'])
        ->and(app(SettingsStore::class)->get(SettingKey::PeriodicRolloutFrom))->toBe('2025-10-01');
});
