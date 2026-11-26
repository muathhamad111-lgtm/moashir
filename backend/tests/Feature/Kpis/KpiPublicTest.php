<?php

declare(strict_types=1);

use App\Enums\Kpis\KpiStatus;
use App\Models\Cms\SiteSettings;
use App\Models\Kpis\Kpi;
use App\Models\Kpis\KpiCategory;
use App\Models\Kpis\KpiType;
use App\Models\Kpis\KpiValue;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('lists published KPIs only, paged, without editor fields', function (): void {
    Kpi::factory()->published()->count(3)->create();
    Kpi::factory()->create(['slug' => 'draft-indicator']);

    $this->getJson('/api/v1/kpis?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonPath('meta.pagination.last_page', 2)
        ->assertJsonMissingPath('data.0.published')
        ->assertJsonMissingPath('data.0.show_on_homepage')
        ->assertJsonMissingPath('data.0.updated_by')
        ->assertJsonPath('data.0.category.name.en', 'Beneficiaries');
});

it('filters by category, status, measurement year, type and owner text and searches by name', function (): void {
    $category = KpiCategory::factory()->create();
    $type = KpiType::factory()->create();
    $match = Kpi::factory()->published()->create([
        'kpi_category_id' => $category->id,
        'kpi_type_id' => $type->id,
        'status' => KpiStatus::Excellent,
        'measurement_year' => 2025,
        'owner' => ['ar' => 'إدارة التطوع', 'en' => 'Volunteering Department'],
        'name' => ['ar' => 'إجمالي ساعات التطوع', 'en' => 'Total volunteer hours'],
    ]);
    Kpi::factory()->published()->create();

    foreach ([
        'filter[category_id]='.$category->id,
        'filter[status]=excellent',
        'filter[measurement_year]=2025',
        'filter[kpi_type_id]='.$type->id,
        'filter[owner]='.rawurlencode('التطوع'),
        'search=volunteer',
    ] as $query) {
        $this->getJson('/api/v1/kpis?'.$query)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }

    $this->getJson('/api/v1/kpis?filter[status]=on_track')->assertUnprocessable()->assertJsonValidationErrors(['filter.status']);
    $this->getJson('/api/v1/kpis?sort=-updated_at')->assertUnprocessable()->assertJsonValidationErrors(['sort']);
});

it('sorts by display order, highest and lowest value, name and newest', function (): void {
    $small = Kpi::factory()->published()->create(['value' => '5', 'sort_order' => 2, 'name' => ['ar' => 'ب', 'en' => 'Beta']]);
    $large = Kpi::factory()->published()->create(['value' => '900', 'sort_order' => 1, 'name' => ['ar' => 'أ', 'en' => 'Alpha']]);
    $empty = Kpi::factory()->published()->create(['value' => null, 'sort_order' => 3, 'name' => ['ar' => 'ج', 'en' => 'Gamma']]);
    $small->forceFill(['created_at' => now()->subDays(3)])->save();
    $large->forceFill(['created_at' => now()->subDays(2)])->save();

    $ids = static fn (string $sort): array => array_column(test()->getJson('/api/v1/kpis?sort='.$sort, ['Accept-Language' => 'en'])->assertOk()->json('data'), 'id');

    expect($ids('order'))->toBe([$large->id, $small->id, $empty->id])
        ->and($ids('-value'))->toBe([$large->id, $small->id, $empty->id])
        ->and($ids('value'))->toBe([$small->id, $large->id, $empty->id])
        ->and($ids('-name'))->toBe([$empty->id, $small->id, $large->id])
        ->and($ids('-created_at'))->toBe([$empty->id, $large->id, $small->id]);
});

it('shows a published KPI by slug with its history and SEO text and hides unpublished ones', function (): void {
    $kpi = Kpi::factory()->published()->create([
        'slug' => 'volunteer-hours',
        'name' => ['ar' => 'ساعات التطوع', 'en' => ''],
        'full_description' => ['ar' => 'إجمالي ساعات التطوع الموثقة.', 'en' => 'Total recorded volunteer hours.'],
    ]);
    KpiValue::query()->create(['kpi_id' => $kpi->id, 'value' => '120', 'measured_on' => '2026-01-31']);
    $draft = Kpi::factory()->create(['slug' => 'draft-indicator']);

    $this->getJson('/api/v1/kpis/volunteer-hours')
        ->assertOk()
        ->assertJsonPath('data.name', ['ar' => 'ساعات التطوع', 'en' => 'ساعات التطوع'])
        ->assertJsonPath('data.history', [['value' => '120', 'measured_on' => '2026-01-31']])
        ->assertJsonPath('data.seo.title.ar', 'ساعات التطوع')
        ->assertJsonPath('data.full_description.en', 'Total recorded volunteer hours.');
    $this->getJson('/api/v1/kpis/'.$draft->slug)->assertNotFound();
    $this->getJson('/api/v1/kpis/unknown-indicator')->assertNotFound();
});

it('features the published homepage KPIs up to the site limit and honours the switch', function (): void {
    Kpi::factory()->published()->featured()->count(5)->create();
    Kpi::factory()->featured()->create();
    Kpi::factory()->published()->create();
    SiteSettings::current()->forceFill(['featured_kpi_limit' => 4])->save();

    $this->getJson('/api/v1/kpis/featured')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('meta.minimum', 3);

    SiteSettings::current()->forceFill(['show_featured_kpis' => false])->save();
    $this->getJson('/api/v1/kpis/featured')->assertOk()->assertJsonCount(0, 'data');
});
