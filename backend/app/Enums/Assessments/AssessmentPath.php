<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

use App\Contracts\Assessments\AssessmentState;
use App\Contracts\Assessments\ReviewedAssessmentState;
use App\Data\Assessments\ReviewStages;
use App\Enums\Entities\EntityRequestPath;
use App\Support\ValueObjects\LocalizedText;
use LogicException;

enum AssessmentPath: string
{
    case SelfAssessment = 'self';
    case Accredited = 'accredited';
    case Advanced = 'advanced';

    public function numberPrefix(): string
    {
        return match ($this) {
            self::SelfAssessment => 'SLF',
            self::Accredited => 'ACC',
            self::Advanced => 'ADV',
        };
    }

    public function initialState(): AssessmentState
    {
        return match ($this) {
            self::SelfAssessment => SelfAssessmentStatus::Draft,
            self::Accredited => AccreditedAssessmentStatus::AwaitingPayment,
            self::Advanced => AdvancedAssessmentStatus::Draft,
        };
    }

    public function stateFrom(string $value): AssessmentState
    {
        return match ($this) {
            self::SelfAssessment => SelfAssessmentStatus::from($value),
            self::Accredited => AccreditedAssessmentStatus::from($value),
            self::Advanced => AdvancedAssessmentStatus::from($value),
        };
    }

    /**
     * @return list<string>
     */
    public function openStateValues(): array
    {
        return array_values(array_map(
            static fn (AssessmentState $state): string => (string) $state->value,
            array_filter($this->states(), static fn (AssessmentState $state): bool => $state->isOpen()),
        ));
    }

    /**
     * @return list<string>
     */
    public function resultStateValues(): array
    {
        return array_values(array_map(
            static fn (AssessmentState $state): string => (string) $state->value,
            array_filter($this->states(), static fn (AssessmentState $state): bool => $state->exposesResult()),
        ));
    }

    public function publishesResultsOnAnnouncement(): bool
    {
        return $this === self::Advanced;
    }

    /**
     * @return list<string>
     */
    public function stateValues(): array
    {
        return array_map(static fn (AssessmentState $state): string => (string) $state->value, $this->states());
    }

    /**
     * @return list<string>
     */
    public function queueStateValues(): array
    {
        return match ($this) {
            self::SelfAssessment => [],
            self::Accredited => AccreditedAssessmentStatus::valuesOf(...AccreditedAssessmentStatus::queueStates()),
            self::Advanced => AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::queueStates()),
        };
    }

    /**
     * @return list<string>
     */
    public function reviewClockStateValues(): array
    {
        return array_values(array_map(
            static fn (AssessmentState $state): string => (string) $state->value,
            array_filter($this->states(), static fn (AssessmentState $state): bool => $state instanceof ReviewedAssessmentState && $state->runsReviewClock()),
        ));
    }

    public function requestPath(): EntityRequestPath
    {
        return match ($this) {
            self::SelfAssessment => EntityRequestPath::SelfAssessment,
            self::Accredited => EntityRequestPath::Accredited,
            self::Advanced => EntityRequestPath::Advanced,
        };
    }

    public function isReviewed(): bool
    {
        return $this !== self::SelfAssessment;
    }

    public function label(): LocalizedText
    {
        return LocalizedText::fromTranslation('assessments.paths.'.$this->value);
    }

    public function followsCriteriaEdits(): bool
    {
        return $this !== self::Advanced;
    }

    public function reviewStages(): ReviewStages
    {
        return match ($this) {
            self::Accredited => new ReviewStages(
                AccreditedAssessmentStatus::Submitted,
                AccreditedAssessmentStatus::Assigned,
                AccreditedAssessmentStatus::UnderReview,
                AccreditedAssessmentStatus::Completed,
                AccreditedAssessmentStatus::NeedsCompletion,
                AccreditedAssessmentStatus::UnderReview,
                AccreditedAssessmentStatus::DeclinedIncomplete,
            ),
            self::Advanced => new ReviewStages(
                AdvancedAssessmentStatus::AwaitingReviewAssignment,
                AdvancedAssessmentStatus::Assigned,
                AdvancedAssessmentStatus::UnderInitialAssessment,
                AdvancedAssessmentStatus::AwaitingFiltering,
                AdvancedAssessmentStatus::NeedsCompletion,
                AdvancedAssessmentStatus::AwaitingReviewAssignment,
                AdvancedAssessmentStatus::DeclinedIncomplete,
            ),
            self::SelfAssessment => throw new LogicException('The self assessment path has no review stages.'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public static function reviewed(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $path): bool => $path->isReviewed()));
    }

    /**
     * @return list<AssessmentState>
     */
    private function states(): array
    {
        return match ($this) {
            self::SelfAssessment => SelfAssessmentStatus::cases(),
            self::Accredited => AccreditedAssessmentStatus::cases(),
            self::Advanced => AdvancedAssessmentStatus::cases(),
        };
    }
}
