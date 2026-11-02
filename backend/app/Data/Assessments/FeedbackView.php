<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\Assessment;

final readonly class FeedbackView
{
    public function __construct(public Assessment $assessment, public FeedbackSheet $sheet, public BoundSubCriteria $subs) {}

    /**
     * @return array<int, array{strengths: string|null, improvement_points: string|null, notes: string|null}>
     */
    public function bySubId(): array
    {
        $fields = [];

        foreach ($this->subs->subs as $sub) {
            $fields[$sub->id] = $this->sheet->fieldsOf($sub->code);
        }

        return $fields;
    }
}
