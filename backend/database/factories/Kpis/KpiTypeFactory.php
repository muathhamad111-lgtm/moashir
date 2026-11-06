<?php

declare(strict_types=1);

namespace Database\Factories\Kpis;

use App\Models\Kpis\KpiType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiType>
 */
final class KpiTypeFactory extends Factory
{
    protected $model = KpiType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->unique()->numberBetween(1, 30_000);

        return [
            'key' => 'outcome-'.$position,
            'name' => ['ar' => 'مؤشر نتائج', 'en' => 'Outcome indicator'],
            'sort_order' => $position,
            'is_active' => true,
        ];
    }
}
