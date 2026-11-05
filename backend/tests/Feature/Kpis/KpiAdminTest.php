<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Kpis\Kpi;
use App\Models\Kpis\KpiCategory;
use App\Models\Kpis\KpiValue;
use App\Notifications\Cms\ContentNotification;
use Illuminate\Support\Facades\Notification;

const KPI_ADMIN = '/api/v1/admin/kpis';

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function kpiPayload(array $overrides = []): array
{
    return [
        'name' => ['ar' => 'المستفيدون من البرامج', 'en' => 'Program beneficiaries'],
        'value' => '1250000',
        'target' => 1500000,
        'baseline' => '900000.50',
        'unit' => ['ar' => 'مستفيد', 'en' => 'beneficiaries'],
        'kpi_category_id' => KpiCategory::factory()->create()->id,
        'status' => 'excellent',
        'measurement_year' => 2026,
        'owner' => ['ar' => 'إدارة الأثر المجتمعي', 'en' => 'Community Impact Department'],
        'external_link' => 'https://example.sa/impact',
        'icon' => 'fa-solid fa-hand-holding-heart',
        'card_color_token' => 'success',
        'badge_color_token' => 'info',
        ...$overrides,
    ];
}

it('creates an unpublished KPI with a generated slug, a first history point, an audit entry and CMS-01', function (): void {
    $officer = contentOfficer();
    $admin = systemAdmin();

    $response = signIn($officer)->postJson(KPI_ADMIN, kpiPayload())
        ->assertCreated()
        ->assertJsonPath('data.slug', 'program-beneficiaries')
        ->assertJsonPath('data.value', '1250000')
        ->assertJsonPath('data.baseline', '900000.5')
        ->assertJsonPath('data.published', false)
        ->assertJsonPath('data.order', 1)
        ->assertJsonPath('data.updated_by.id', $officer->id);

    $kpi = Kpi::query()->findOrFail($response->json('data.id'));
    expect(KpiValue::query()->where('kpi_id', $kpi->id)->sole()->value->value)->toBe('1250000')
        ->and(Audit::query()->where('event', AuditEvent::KpiCreated->value)->count())->toBe(1);
    Notification::assertSentTo($admin, ContentNotification::class, static fn (ContentNotification $notification): bool => $notification->notice->code === NotificationCode::KpiAdded);
    Notification::assertNotSentTo($officer, ContentNotification::class);
});

it('denies KPI administration to roles without content.kpis.manage', function (RoleKey $role): void {
    $kpi = Kpi::factory()->create();
    signIn($role === RoleKey::Entity ? entityUser() : staffUser($role));

    $this->getJson(KPI_ADMIN)->assertForbidden();
    $this->postJson(KPI_ADMIN, kpiPayload())->assertForbidden();
    $this->patchJson(KPI_ADMIN.'/'.$kpi->id.'/published', ['published' => true])->assertForbidden();
    $this->patchJson(KPI_ADMIN.'/reorder', ['ordered_ids' => [$kpi->id]])->assertForbidden();
    $this->postJson('/api/v1/admin/kpi-types', ['key' => 'outcome', 'name' => ['ar' => 'نتائج']])->assertForbidden();
})->with([RoleKey::FinanceOfficer, RoleKey::CriteriaOfficer, RoleKey::Entity]);

it('rejects invalid KPI input', function (array $overrides, string $field): void {
    signIn(contentOfficer())->postJson(KPI_ADMIN, kpiPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing name' => [['name' => ['ar' => '', 'en' => '']], 'name.ar'],
    'float-like value' => [['value' => '1.23456'], 'value'],
    'text value' => [['value' => 'many'], 'value'],
    'unknown status' => [['status' => 'on_track'], 'status'],
    'free colour' => [['card_color_token' => '#ff0000'], 'card_color_token'],
    'http link' => [['external_link' => 'http://example.sa'], 'external_link'],
    'bad icon' => [['icon' => 'chart'], 'icon'],
    'year out of range' => [['measurement_year' => 1990], 'measurement_year'],
    'unknown category' => [['kpi_category_id' => 999999], 'kpi_category_id'],
    'published flag' => [['published' => true], 'published'],
    'bad slug' => [['slug' => 'Not A Slug'], 'slug'],
]);

it('refuses a slug that another KPI uses', function (): void {
    Kpi::factory()->create(['slug' => 'volunteer-hours']);

    signIn(contentOfficer())->postJson(KPI_ADMIN, kpiPayload(['slug' => 'volunteer-hours']))
        ->assertUnprocessable()->assertJsonPath('code', 'slug.taken');
});

it('records the value history and sends CMS-02 only when the value changes', function (): void {
    $kpi = Kpi::factory()->create(['value' => '100']);
    $admin = systemAdmin();
    signIn(contentOfficer());

    $this->putJson(KPI_ADMIN.'/'.$kpi->id, ['notes' => ['ar' => 'ملاحظة', 'en' => 'Note']])->assertOk();
    $this->putJson(KPI_ADMIN.'/'.$kpi->id, ['value' => '100.0'])->assertOk();
    Notification::assertNothingSent();

    $this->putJson(KPI_ADMIN.'/'.$kpi->id, ['value' => '125.5'])
        ->assertOk()
        ->assertJsonPath('data.value', '125.5')
        ->assertJsonPath('data.last_update', fn (?string $value): bool => $value !== null);

    expect(KpiValue::query()->where('kpi_id', $kpi->id)->sole()->value->value)->toBe('125.5');
    $audit = Audit::query()->where('event', AuditEvent::KpiUpdated->value)->latest('id')->firstOrFail();
    expect($audit->old_values['value'])->toBe('100')->and($audit->new_values['value'])->toBe('125.5');
    Notification::assertSentTo($admin, ContentNotification::class, static fn (ContentNotification $notification): bool => $notification->notice->code === NotificationCode::KpiValueEdited);
});

it('publishes and unpublishes a KPI with CMS-03 and CMS-05', function (): void {
    $kpi = Kpi::factory()->create();
    $admin = systemAdmin();
    signIn(contentOfficer());

    $this->patchJson(KPI_ADMIN.'/'.$kpi->id.'/published', ['published' => true])
        ->assertOk()->assertJsonPath('data.published', true);
    $this->patchJson(KPI_ADMIN.'/'.$kpi->id.'/published', ['published' => true])->assertOk();
    $this->patchJson(KPI_ADMIN.'/'.$kpi->id.'/published', ['published' => false])
        ->assertOk()->assertJsonPath('data.published', false);
    $this->patchJson(KPI_ADMIN.'/'.$kpi->id.'/published', [])->assertUnprocessable();

    Notification::assertSentToTimes($admin, ContentNotification::class, 2);
    expect(Audit::query()->where('event', AuditEvent::KpiPublished->value)->count())->toBe(1);
});

it('moves a KPI up and down and reorders the whole list by drag and drop', function (): void {
    $first = Kpi::factory()->create(['sort_order' => 1]);
    $second = Kpi::factory()->create(['sort_order' => 2]);
    $third = Kpi::factory()->create(['sort_order' => 3]);
    signIn(contentOfficer());

    $this->patchJson(KPI_ADMIN.'/'.$third->id.'/reorder', ['direction' => 'up'])
        ->assertOk()->assertJsonPath('data.1.id', $third->id);
    $this->patchJson(KPI_ADMIN.'/'.$first->id.'/reorder', ['direction' => 'up'])
        ->assertOk()->assertJsonPath('data.0.id', $first->id);
    $this->patchJson(KPI_ADMIN.'/reorder', ['ordered_ids' => [$second->id, $first->id, $third->id]])
        ->assertOk()
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.2.order', 3);
    $this->patchJson(KPI_ADMIN.'/reorder', ['ordered_ids' => [$second->id, $first->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'order.incomplete');
    $this->patchJson(KPI_ADMIN.'/'.$first->id.'/reorder', ['direction' => 'left'])->assertUnprocessable();
});

it('archives a KPI, hides it everywhere and closes the gap in the order', function (): void {
    $first = Kpi::factory()->published()->featured()->create(['sort_order' => 1]);
    $second = Kpi::factory()->create(['sort_order' => 2]);
    signIn(contentOfficer());

    $this->deleteJson(KPI_ADMIN.'/'.$first->id)->assertNoContent();

    $this->getJson(KPI_ADMIN.'/'.$first->id)->assertNotFound();
    $this->getJson('/api/v1/kpis/'.$first->slug)->assertNotFound();
    expect($second->refresh()->sort_order)->toBe(1)
        ->and(Kpi::withTrashed()->findOrFail($first->id)->is_published)->toBeFalse();
});

it('keeps at most six KPIs on the homepage', function (): void {
    Kpi::factory()->count(6)->featured()->create();
    $kpi = Kpi::factory()->create();
    signIn(contentOfficer());

    $this->putJson(KPI_ADMIN.'/'.$kpi->id, ['show_on_homepage' => true])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'kpi.featured_limit_reached')
        ->assertJsonPath('meta.limit', 6);
});

it('lists KPIs for the editor with search, filters and statistics', function (): void {
    Kpi::factory()->published()->create(['name' => ['ar' => 'ساعات التطوع', 'en' => 'Volunteer hours']]);
    Kpi::factory()->create(['name' => ['ar' => 'رضا المستفيدين', 'en' => 'Beneficiary satisfaction']]);
    signIn(contentOfficer());

    $this->getJson(KPI_ADMIN.'?search='.rawurlencode('التطوع'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name.en', 'Volunteer hours');
    $this->getJson(KPI_ADMIN.'?filter[published]=false')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson(KPI_ADMIN)
        ->assertOk()
        ->assertJsonPath('meta.stats', ['total' => 2, 'published' => 1, 'unpublished' => 1, 'featured' => 0])
        ->assertJsonPath('meta.pagination.total', 2);
    $this->getJson(KPI_ADMIN.'?sort=-value')->assertUnprocessable()->assertJsonValidationErrors(['sort']);
});

it('records a dated measurement, refuses a duplicate date and moves the current value only for the latest date', function (): void {
    $kpi = Kpi::factory()->create(['value' => '50']);
    KpiValue::query()->create(['kpi_id' => $kpi->id, 'value' => '50', 'measured_on' => '2026-06-30']);
    signIn(contentOfficer());

    $this->postJson(KPI_ADMIN.'/'.$kpi->id.'/values', ['value' => '40', 'measured_on' => '2026-03-31'])
        ->assertCreated()->assertJsonCount(2, 'data')->assertJsonPath('data.0.measured_on', '2026-03-31');
    expect($kpi->refresh()->value?->value)->toBe('50');

    $this->postJson(KPI_ADMIN.'/'.$kpi->id.'/values', ['value' => '61', 'measured_on' => '2026-09-30'])->assertCreated();
    expect($kpi->refresh()->value?->value)->toBe('61');

    $this->postJson(KPI_ADMIN.'/'.$kpi->id.'/values', ['value' => '62', 'measured_on' => '2026-09-30'])
        ->assertUnprocessable()->assertJsonPath('code', 'kpi.measurement_exists');
    $this->postJson(KPI_ADMIN.'/'.$kpi->id.'/values', ['value' => '62', 'measured_on' => '2099-01-01'])
        ->assertUnprocessable()->assertJsonValidationErrors(['measured_on']);
    $this->getJson(KPI_ADMIN.'/'.$kpi->id.'/values')->assertOk()->assertJsonCount(3, 'data');
});

it('attaches an uploaded KPI image and removes the replaced one', function (): void {
    $kpi = Kpi::factory()->create();
    signIn(contentOfficer());
    $first = $this->post(KPI_ADMIN.'/images', ['file' => pngImage()], ['Accept' => 'application/json'])->assertCreated()->json('data.file_id');
    $second = $this->post(KPI_ADMIN.'/images', ['file' => pngImage()], ['Accept' => 'application/json'])->assertCreated()->json('data.file_id');

    $this->putJson(KPI_ADMIN.'/'.$kpi->id, ['image_file_id' => $first])->assertOk()->assertJsonPath('data.image.file_id', $first);
    $this->putJson(KPI_ADMIN.'/'.$kpi->id, ['image_file_id' => $second])->assertOk()->assertJsonPath('data.image.file_id', $second);

    $this->get('/api/v1/content/files/'.$first)->assertNotFound();
    $this->get('/api/v1/content/files/'.$second)->assertOk();
});
