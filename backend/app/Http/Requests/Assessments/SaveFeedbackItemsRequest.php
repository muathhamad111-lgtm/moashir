<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Data\Assessments\FeedbackChanges;
use App\Enums\Assessments\FeedbackKind;
use Illuminate\Foundation\Http\FormRequest;

final class SaveFeedbackItemsRequest extends FormRequest
{
    private const int MAX_ITEMS = 500;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maximum = config()->integer('moashir.assessment.feedback_max_chars');

        return [
            'feedback' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'feedback.*' => ['required', 'array:'.implode(',', FeedbackKind::fields())],
            'feedback.*.*' => ['nullable', 'string', 'max:'.$maximum],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function changes(): FeedbackChanges
    {
        $changes = [];

        foreach ((array) $this->validated('feedback') as $subId => $fields) {
            foreach (is_array($fields) ? $fields : [] as $field => $body) {
                $changes[$subId][(string) $field] = is_string($body) ? $body : null;
            }
        }

        return new FeedbackChanges($changes);
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
