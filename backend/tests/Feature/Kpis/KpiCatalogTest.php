<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Models\Audit\Audit;
use App\Models\Kpis\Kpi;
use App\Models\Kpis\KpiCategory;
use App\Models\Kpis\KpiType;

it('manages KPI categories and refuses to remove one that indicators use', function (): void {
    signIn(contentOfficer());

    $id = $this->postJson('/api/v1/admin/kpi-categories', [
        'key' => 'human-resources',
        'name' => ['ar' => 'الموارد البشرية', 'en' => 'Human resources'],
        'icon' => 'fa-solid fa-user-tie',
    ])->assertCreated()->assertJsonPath('data.order', 1)->json('data.id');

    $this->postJson('/api/v1/admin/kpi-categories', ['key' => 'human-resources', 'name' => ['ar' => 'مكرر']])
        ->assertUnprocessable()->assertJsonValidationErrors(['key']);
    $this->putJson('/api/v1/admin/kpi-categories/'.$id, ['active' => false])->assertOk()->assertJsonPath('data.active', false);

    Kpi::factory()->create(['kpi_category_id' => $id]);
    $this->deleteJson('/api/v1/admin/kpi-categories/'.$id)->assertConflict()->assertJsonPath('code', 'category.in_use')->assertJsonPath('meta.kpi_count', 1);

    $unused = KpiCategory::factory()->create();
    $this->deleteJson('/api/v1/admin/kpi-categories/'.$unused->id)->assertNoContent();
    expect(Audit::query()->where('event', AuditEvent::KpiCategoryRemoved->value)->count())->toBe(1);
});

it('manages the KPI type list of Q-40 and refuses to delete a type in use', function (): void {
    signIn(contentOfficer());

    $id = $this->postJson('/api/v1/admin/kpi-types', ['key' => 'outcome', 'name' => ['ar' => 'مؤشر نتائج', 'en' => 'Outcome indicator']])
        ->assertCreated()->json('data.id');
    $this->postJson('/api/v1/admin/kpi-types', ['key' => 'output', 'name' => ['ar' => 'مؤشر مخرجات'], 'icon' => 'fa-solid fa-star'])
        ->assertUnprocessable()->assertJsonValidationErrors(['icon']);

    Kpi::factory()->create(['kpi_type_id' => $id]);
    $this->deleteJson('/api/v1/admin/kpi-types/'.$id)->assertConflict()->assertJsonPath('code', 'type.in_use');

    $unused = KpiType::factory()->create();
    $this->deleteJson('/api/v1/admin/kpi-types/'.$unused->id)->assertNoContent();
    expect(KpiType::query()->find($unused->id))->toBeNull();
});

it('publishes only active categories and types to the public filters', function (): void {
    KpiCategory::factory()->create(['key' => 'innovation', 'name' => ['ar' => 'الابتكار', 'en' => '']]);
    KpiCategory::factory()->create(['key' => 'retired', 'is_active' => false]);
    KpiType::factory()->create(['key' => 'input']);
    KpiType::factory()->create(['key' => 'legacy', 'is_active' => false]);

    $this->getJson('/api/v1/kpi-categories')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.key', 'innovation');
    $this->getJson('/api/v1/kpi-types')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.key', 'input');
});
