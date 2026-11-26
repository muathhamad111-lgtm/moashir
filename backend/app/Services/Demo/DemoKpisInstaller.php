<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use App\Models\Kpis\KpiCategory;
use App\Models\Kpis\KpiType;
use App\Models\Kpis\KpiValue;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;

final class DemoKpisInstaller
{
    private const array LOCALIZED_FIELDS = ['name', 'unit', 'short_description', 'owner'];

    /**
     * @param  array<string, mixed>  $content
     */
    public function install(User $editor, array $content): int
    {
        if (Kpi::withTrashed()->exists()) {
            return 0;
        }

        $categories = $this->catalog(KpiCategory::class, DemoContentSource::listOf($content, 'kpi_categories'));
        $types = $this->catalog(KpiType::class, DemoContentSource::listOf($content, 'kpi_types'));
        $installed = 0;

        foreach (DemoContentSource::listOf($content, 'kpis') as $position => $data) {
            $this->createKpi($editor, $data, $position + 1, $categories[ScalarValue::string($data['category'] ?? null)] ?? null, $types[ScalarValue::string($data['type'] ?? null)] ?? null);
            $installed++;
        }

        return $installed;
    }

    /**
     * @param  class-string<KpiCategory|KpiType>  $model
     * @param  list<array<array-key, mixed>>  $entries
     * @return array<string, int>
     */
    private function catalog(string $model, array $entries): array
    {
        $ids = [];

        foreach ($entries as $position => $entry) {
            $key = ScalarValue::string($entry['key'] ?? null);
            $attributes = ['name' => $entry['name'] ?? null, 'sort_order' => $position + 1, 'is_active' => true];

            if ($model === KpiCategory::class) {
                $attributes['icon'] = $entry['icon'] ?? null;
            }

            $ids[$key] = $model::query()->firstOrCreate(['key' => $key], $attributes)->id;
        }

        return $ids;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function createKpi(User $editor, array $data, int $position, ?int $categoryId, ?int $typeId): void
    {
        $kpi = new Kpi;
        $attributes = [
            'slug' => $data['slug'] ?? 'kpi-'.$position,
            'kpi_category_id' => $categoryId,
            'kpi_type_id' => $typeId,
            'value' => $data['value'] ?? null,
            'target' => $data['target'] ?? null,
            'baseline' => $data['baseline'] ?? null,
            'measurement_year' => (int) Date::now()->format('Y'),
            'status' => $data['status'] ?? null,
            'icon' => $data['icon'] ?? null,
            'card_color_token' => $data['card'] ?? null,
            'badge_color_token' => $data['badge'] ?? null,
            'show_on_homepage' => ($data['featured'] ?? false) === true,
        ];

        foreach (self::LOCALIZED_FIELDS as $field) {
            $attributes[$field] = $data[$field] ?? null;
        }

        $kpi->fill($attributes);
        $kpi->forceFill([
            'sort_order' => $position,
            'is_published' => true,
            'published_at' => Date::now(),
            'value_updated_at' => Date::now(),
            'updated_by_user_id' => $editor->id,
        ])->save();

        if ($kpi->value !== null) {
            KpiValue::query()->create(['kpi_id' => $kpi->id, 'value' => $kpi->value, 'measured_on' => Date::now()->toDateString(), 'recorded_by_user_id' => $editor->id]);
        }
    }
}
