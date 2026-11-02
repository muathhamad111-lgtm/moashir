<?php

declare(strict_types=1);

namespace Database\Factories\Criteria;

use App\Enums\Criteria\CriterionGroup;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use App\Support\ValueObjects\Weight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MainCriterion>
 */
final class MainCriterionFactory extends Factory
{
    protected $model = MainCriterion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->unique()->numberBetween(1, 30_000);

        return [
            'criteria_version_id' => CriteriaVersion::factory(),
            'code' => 'M'.$position,
            'name' => ['ar' => 'القيادة الإدارية', 'en' => 'Administrative Leadership'],
            'description' => ['ar' => 'قدرة القيادة على توجيه الجهة.', 'en' => 'Leadership ability to steer the organisation.'],
            'weight' => Weight::of('1000.00'),
            'distribute_equally' => false,
            'group' => CriterionGroup::Enablers,
            'is_active' => true,
            'sort_order' => $position,
        ];
    }

    public function weighing(string $weight): self
    {
        return $this->state(['weight' => Weight::of($weight)]);
    }
}
