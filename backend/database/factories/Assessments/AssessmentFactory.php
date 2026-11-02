<?php

declare(strict_types=1);

namespace Database\Factories\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Enums\Criteria\ScaleKind;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
final class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_number' => sprintf('SLF-2026-%06d', fake()->unique()->numberBetween(900_000, 999_999)),
            'entity_id' => Entity::factory()->withOwner(),
            'path' => AssessmentPath::SelfAssessment,
            'status' => SelfAssessmentStatus::Draft,
            'criteria_version_id' => CriteriaVersion::factory(),
            'scale_kind' => ScaleKind::OutOfTen,
        ];
    }

    public function forEntity(Entity $entity): self
    {
        return $this->state(['entity_id' => $entity->id]);
    }

    public function boundTo(CriteriaVersion $version): self
    {
        return $this->state(['criteria_version_id' => $version->id, 'scale_kind' => $version->scale_kind ?? ScaleKind::OutOfTen]);
    }
}
