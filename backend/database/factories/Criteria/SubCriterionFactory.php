<?php

declare(strict_types=1);

namespace Database\Factories\Criteria;

use App\Enums\Criteria\EvaluationType;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Support\ValueObjects\Weight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubCriterion>
 */
final class SubCriterionFactory extends Factory
{
    protected $model = SubCriterion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->unique()->numberBetween(1, 30_000);

        return [
            'main_criterion_id' => MainCriterion::factory(),
            'code' => 'S'.$position,
            'name' => ['ar' => 'الحوكمة والامتثال', 'en' => 'Governance and Compliance'],
            'description' => null,
            'weight' => Weight::of('500.00'),
            'distribute_equally' => false,
            'evaluation_type' => EvaluationType::GuidingPoints,
            'has_cv' => true,
            'is_active' => true,
            'sort_order' => $position,
        ];
    }

    public function direct(): self
    {
        return $this->state(['evaluation_type' => EvaluationType::Direct]);
    }

    public function weighing(string $weight): self
    {
        return $this->state(['weight' => Weight::of($weight)]);
    }
}
