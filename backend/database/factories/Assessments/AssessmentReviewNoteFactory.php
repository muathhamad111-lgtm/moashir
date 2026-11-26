<?php

declare(strict_types=1);

namespace Database\Factories\Assessments;

use App\Enums\Assessments\ReviewNoteAuthorRole;
use App\Enums\Assessments\ReviewNoteLevel;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentReviewNote;
use App\Models\Criteria\MainCriterion;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentReviewNote>
 */
final class AssessmentReviewNoteFactory extends Factory
{
    protected $model = AssessmentReviewNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'level' => ReviewNoteLevel::Main,
            'main_criterion_id' => MainCriterion::factory(),
            'sub_criterion_id' => null,
            'guiding_point_id' => null,
            'author_user_id' => User::factory(),
            'author_role' => ReviewNoteAuthorRole::Follower,
            'body' => 'يحتاج هذا المعيار إلى مراجعة الشواهد المرفوعة مع فريق المتابعة.',
            'version' => 1,
        ];
    }

    public function on(Assessment $assessment): self
    {
        return $this->state(['assessment_id' => $assessment->id]);
    }

    public function by(User $author, ReviewNoteAuthorRole $role): self
    {
        return $this->state(['author_user_id' => $author->id, 'author_role' => $role]);
    }
}
