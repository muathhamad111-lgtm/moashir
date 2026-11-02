<?php

declare(strict_types=1);

namespace Database\Factories\Criteria;

use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\SubCriterion;
use App\Support\ValueObjects\Weight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuidingPoint>
 */
final class GuidingPointFactory extends Factory
{
    protected $model = GuidingPoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->unique()->numberBetween(1, 30_000);

        return [
            'sub_criterion_id' => SubCriterion::factory(),
            'code' => 'P'.$position,
            'description' => ['ar' => 'اعتماد لائحة حوكمة تحدد الأدوار والمسؤوليات.', 'en' => 'An approved governance charter defining roles and responsibilities.'],
            'weight' => Weight::of('250.00'),
            'is_active' => true,
            'sort_order' => $position,
        ];
    }

    public function weighing(string $weight): self
    {
        return $this->state(['weight' => Weight::of($weight)]);
    }
}
