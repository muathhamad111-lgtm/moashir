<?php

declare(strict_types=1);

namespace Database\Factories\Entities;

use App\Models\Entities\City;
use App\Models\Entities\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
final class CityFactory extends Factory
{
    protected $model = City::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->unique()->numberBetween(1, 900_000);

        return [
            'region_id' => Region::factory(),
            'code' => 'city_'.$position,
            'name' => ['ar' => 'الرياض', 'en' => 'Riyadh'],
            'sort_order' => $position % 30_000,
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
