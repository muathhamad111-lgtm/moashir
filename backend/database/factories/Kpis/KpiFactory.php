<?php

declare(strict_types=1);

namespace Database\Factories\Kpis;

use App\Enums\Kpis\KpiColorToken;
use App\Enums\Kpis\KpiStatus;
use App\Models\Kpis\Kpi;
use App\Models\Kpis\KpiCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Kpi>
 */
final class KpiFactory extends Factory
{
    protected $model = Kpi::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->unique()->numberBetween(1, 30_000);

        return [
            'slug' => 'participating-associations-'.$position,
            'name' => ['ar' => 'الجمعيات المشاركة', 'en' => 'Participating associations'],
            'kpi_category_id' => KpiCategory::factory(),
            'value' => '486',
            'target' => '700',
            'baseline' => '320',
            'unit' => ['ar' => 'جمعية', 'en' => 'associations'],
            'short_description' => ['ar' => 'عدد الجمعيات المسجلة والمشاركة في المنصة.', 'en' => 'Associations registered and taking part in the platform.'],
            'owner' => ['ar' => 'إدارة تسجيل الجهات', 'en' => 'Entity Registration Department'],
            'measurement_year' => 2026,
            'status' => KpiStatus::Good,
            'icon' => 'fa-solid fa-people-group',
            'card_color_token' => KpiColorToken::Primary,
            'badge_color_token' => KpiColorToken::Success,
            'sort_order' => $position,
        ];
    }

    public function published(): self
    {
        return $this->afterMaking(static function (Kpi $kpi): void {
            $kpi->forceFill(['is_published' => true, 'published_at' => Date::now(), 'value_updated_at' => Date::now()]);
        });
    }

    public function featured(): self
    {
        return $this->state(['show_on_homepage' => true]);
    }
}
