<?php

declare(strict_types=1);

namespace Database\Factories\Criteria;

use App\Models\Criteria\CriteriaVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CriteriaVersion>
 */
final class CriteriaVersionFactory extends Factory
{
    protected $model = CriteriaVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version_number' => fake()->unique()->numberBetween(100, 100_000),
            'label' => ['ar' => 'نموذج التميز المؤسسي', 'en' => 'Institutional Excellence Model'],
        ];
    }
}
