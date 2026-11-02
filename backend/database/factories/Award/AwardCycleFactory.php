<?php

declare(strict_types=1);

namespace Database\Factories\Award;

use App\Enums\Award\AwardCycleStatus;
use App\Enums\Criteria\ScaleKind;
use App\Models\Award\AwardCycle;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Support\ValueObjects\Percent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<AwardCycle>
 */
final class AwardCycleFactory extends Factory
{
    protected $model = AwardCycle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Date::now()->addDays(2)->startOfHour();

        return [
            'name' => ['ar' => 'الدورة الأولى لجائزة التميز المؤسسي', 'en' => 'First Institutional Excellence Award Cycle'],
            'year' => $start->year,
            'status' => AwardCycleStatus::Draft,
            'criteria_version_id' => CriteriaVersion::factory(),
            'scale_kind' => ScaleKind::OutOfTen,
            'submission_start' => $start,
            'submission_end' => $start->copy()->addDays(30),
            'committee_start' => $start->copy()->addDays(45),
            'committee_end' => $start->copy()->addDays(75),
            'announcement_at' => $start->copy()->addDays(90),
            'min_entity_age_years' => 3,
            'disqualification_score' => Percent::of('60'),
            'initial_review_sla_days' => 14,
            'participation_certificate_enabled' => false,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function boundTo(CriteriaVersion $version): self
    {
        return $this->state(['criteria_version_id' => $version->id, 'scale_kind' => $version->scale_kind ?? ScaleKind::OutOfTen]);
    }
}
