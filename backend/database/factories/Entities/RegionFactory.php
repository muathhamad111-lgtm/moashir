<?php

declare(strict_types=1);

namespace Database\Factories\Entities;

use App\Models\Entities\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
final class RegionFactory extends Factory
{
    protected $model = Region::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->unique()->numberBetween(1, 900_000);

        return [
            'code' => 'region_'.$position,
            'name' => ['ar' => 'منطقة الرياض', 'en' => 'Riyadh Region'],
            'sort_order' => $position % 30_000,
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
