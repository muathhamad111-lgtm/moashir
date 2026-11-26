<?php

declare(strict_types=1);

use App\Data\Dashboards\BoardFilter;
use App\Enums\Contact\ContactMessageStatus;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\UserStatus;
use App\Models\Entities\Region;
use App\Models\Identity\AuthenticationEvent;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use App\Models\Kpis\KpiValue;
use App\Models\Platform\StorageMeasurement;
use App\Services\Dashboards\Breakdowns\EntityProfileBreakdown;
use App\Services\Dashboards\Breakdowns\InboxBreakdown;
use App\Services\Dashboards\Breakdowns\KpiTargetBreakdown;
use App\Services\Dashboards\Breakdowns\StorageUsage;
use App\Services\Dashboards\Breakdowns\UserAccountBreakdown;
use App\Services\Dashboards\Overview\ReportingPeriods;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow('2026-11-17 08:00:00');
});

it('counts the entities as they stood at the period end by type, size and region', function (): void {
    $riyadh = Region::factory()->create(['sort_order' => 1]);
    $makkah = Region::factory()->create(['sort_order' => 2]);
    profiledEntity(['entity_type' => EntityType::Waqf, 'org_size' => OrgSize::Small], $makkah);
    profiledEntity(['entity_type' => EntityType::Waqf, 'org_size' => OrgSize::Large], $makkah);
    profiledEntity(['entity_type' => EntityType::CivilAssociation, 'org_size' => OrgSize::Small], $riyadh);
    profiledEntity(['entity_type' => EntityType::CivilAssociation], $riyadh)->delete();
    Region::factory()->create();

    expect(breakdownOf(EntityProfileBreakdown::class, 'entities_by_type'))->toBe([
        ['key' => 'civil_association', 'count' => 1], ['key' => 'civil_foundation', 'count' => 0], ['key' => 'waqf', 'count' => 2],
    ])->and(breakdownOf(EntityProfileBreakdown::class, 'entities_by_size'))->toBe([
        ['key' => 'small', 'count' => 2], ['key' => 'medium', 'count' => 0], ['key' => 'large', 'count' => 1],
    ])->and(breakdownOf(EntityProfileBreakdown::class, 'entities_by_region'))->toBe([
        ['key' => $makkah->code, 'name' => $makkah->name->toArray(), 'count' => 2],
        ['key' => $riyadh->code, 'name' => $riyadh->name->toArray(), 'count' => 1],
    ])->and(breakdownOf(EntityProfileBreakdown::class, 'entities_by_region', new BoardFilter(region: $riyadh->code)))->toHaveCount(1);
});

it('counts an entity removed after the period end and leaves out one registered after it', function (): void {
    $october = app(ReportingPeriods::class)->custom('2026-10-01', '2026-10-31');
    $removedLater = profiledEntity(['entity_type' => EntityType::Waqf]);
    $removedLater->forceFill(['created_at' => '2026-10-05 09:00:00'])->saveQuietly();
    $removedLater->delete();
    $removedBefore = profiledEntity(['entity_type' => EntityType::Waqf]);
    $removedBefore->forceFill(['created_at' => '2026-10-05 09:00:00', 'deleted_at' => '2026-10-20 09:00:00'])->saveQuietly();
    profiledEntity(['entity_type' => EntityType::Waqf]);

    expect(collect(breakdownOf(EntityProfileBreakdown::class, 'entities_by_type', null, $october))->pluck('count', 'key')->all()['waqf'])->toBe(1);
});

it('lists the published KPIs with a target, valued as at the period end', function (): void {
    $measured = Kpi::factory()->published()->create(['slug' => 'measured', 'sort_order' => 1, 'value' => '10', 'target' => '80']);
    KpiValue::query()->create(['kpi_id' => $measured->id, 'value' => '62', 'measured_on' => '2026-11-10']);
    KpiValue::query()->create(['kpi_id' => $measured->id, 'value' => '75', 'measured_on' => '2026-11-30']);
    Kpi::factory()->published()->create(['slug' => 'unmeasured', 'sort_order' => 2, 'value' => '12.5', 'target' => '20']);
    Kpi::factory()->published()->create(['slug' => 'untargeted', 'sort_order' => 3, 'target' => null]);
    Kpi::factory()->create(['slug' => 'draft', 'sort_order' => 4]);

    expect(breakdownOf(KpiTargetBreakdown::class, 'kpi_targets'))->toBe([
        ['key' => 'measured', 'name' => $measured->name->toArray(), 'value' => 62, 'target' => 80],
        ['key' => 'unmeasured', 'name' => $measured->name->toArray(), 'value' => 12.5, 'target' => 20],
    ]);
});

it('keeps the KPIs to six', function (): void {
    Kpi::factory()->published()->count(7)->create();

    expect(breakdownOf(KpiTargetBreakdown::class, 'kpi_targets'))->toHaveCount(6);
});

it('counts the messages received and replied in the period with the average reply time', function (): void {
    contactMessage(['created_at' => '2026-11-02 08:00:00', 'replied_at' => '2026-11-02 14:00:00', 'status' => ContactMessageStatus::Replied]);
    contactMessage(['created_at' => '2026-11-03 08:00:00', 'replied_at' => '2026-11-03 20:00:00', 'status' => ContactMessageStatus::Replied]);
    contactMessage(['created_at' => '2026-11-04 08:00:00', 'status' => ContactMessageStatus::InProgress]);
    contactMessage(['created_at' => '2026-10-20 08:00:00', 'replied_at' => '2026-11-01 08:00:00', 'status' => ContactMessageStatus::Replied]);
    contactMessage(['created_at' => '2026-10-20 08:00:00']);

    expect(breakdownOf(InboxBreakdown::class, 'inbox'))->toBe(['received' => 3, 'awaiting_reply' => 1, 'replied' => 3, 'average_reply_hours' => 102.0]);
});

it('has no average reply time without replies', function (): void {
    expect(breakdownOf(InboxBreakdown::class, 'inbox'))->toBe(['received' => 0, 'awaiting_reply' => 0, 'replied' => 0, 'average_reply_hours' => null]);
});

it('counts the staff and entity accounts, the MFA share, locked accounts and failed sign-ins', function (): void {
    expect(breakdownOf(UserAccountBreakdown::class, 'users')['mfa_share'])->toBeNull();

    systemAdmin();
    User::factory()->create();
    User::factory()->create(['status' => UserStatus::Suspended]);
    User::factory()->create(['locked_until' => '2026-11-17 08:30:00']);
    User::factory()->create(['locked_until' => '2026-11-17 07:30:00']);
    profiledEntity();
    superAdmin();
    AuthenticationEvent::query()->create(['type' => AuthEventType::LoginFailed]);

    expect(breakdownOf(UserAccountBreakdown::class, 'users'))->toBe([
        'staff_active' => 4, 'entity_accounts' => 1, 'mfa_share' => 25.0, 'locked' => 1, 'failed_logins' => 1,
    ]);
});

it('reports the larger of the stored and measured bytes against the configured quota', function (): void {
    config(['moashir.storage' => ['quota_gb' => 10, 'warning_percent' => 70, 'critical_percent' => 90]]);
    storedTestFile();
    storedTestFile(['created_at' => '2026-10-01 09:00:00']);
    storedTestFile()->delete();

    $unmeasured = breakdownOf(StorageUsage::class, 'storage');

    expect($unmeasured)->toBe([
        'quota_bytes' => 10 * 1024 ** 3,
        'used_bytes' => 2 * strlen(SAMPLE_PDF),
        'added_in_period_bytes' => 2 * strlen(SAMPLE_PDF),
        'warning_percent' => 70,
        'critical_percent' => 90,
        'measured_at' => null,
    ]);

    StorageMeasurement::query()->create(['measured_on' => '2026-11-17', 'used_bytes' => 5_000_000, 'file_count' => 9, 'measured_at' => '2026-11-16 23:15:00']);

    expect(breakdownOf(StorageUsage::class, 'storage'))->toMatchArray(['used_bytes' => 5_000_000, 'measured_at' => '2026-11-16T23:15:00+00:00']);
});
