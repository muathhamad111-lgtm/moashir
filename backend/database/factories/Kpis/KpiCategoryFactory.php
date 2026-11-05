<?php

declare(strict_types=1);

namespace Database\Factories\Kpis;

use App\Models\Kpis\KpiCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiCategory>
 */
final class KpiCategoryFactory extends Factory
{
    protected $model = KpiCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->unique()->numberBetween(1, 30_000);

        return [
            'key' => 'beneficiaries-'.$position,
            'name' => ['ar' => 'المستفيدون', 'en' => 'Beneficiaries'],
            'sort_order' => $position,
            'is_active' => true,
        ];
    }
}
