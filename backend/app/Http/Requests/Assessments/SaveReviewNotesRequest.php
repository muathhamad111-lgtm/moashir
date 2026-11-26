<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Data\Assessments\ReviewNoteChange;
use App\Data\Assessments\ReviewNoteChanges;
use App\Data\Assessments\ReviewNoteUnit;
use App\Enums\Assessments\ReviewNoteLevel;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SaveReviewNotesRequest extends FormRequest
{
    private const int MAX_NOTES = 500;

    private const array ENTRY_KEYS = ['level', 'main_criterion_id', 'sub_criterion_id', 'guiding_point_id', 'body'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $levels = 'notes.*.level';

        return [
            'notes' => ['required', 'list', 'min:1', 'max:'.self::MAX_NOTES],
            'notes.*' => ['required', 'array:'.implode(',', self::ENTRY_KEYS)],
            $levels => ['required', 'string', Rule::enum(ReviewNoteLevel::class)],
            'notes.*.main_criterion_id' => ['required', 'integer', 'min:1'],
            'notes.*.sub_criterion_id' => [
                'nullable', 'integer', 'min:1',
                'required_if:'.$levels.','.ReviewNoteLevel::Sub->value.','.ReviewNoteLevel::Point->value,
                'prohibited_if:'.$levels.','.ReviewNoteLevel::Main->value,
            ],
            'notes.*.guiding_point_id' => [
                'nullable', 'integer', 'min:1',
                'required_if:'.$levels.','.ReviewNoteLevel::Point->value,
                'prohibited_unless:'.$levels.','.ReviewNoteLevel::Point->value,
            ],
            'notes.*.body' => ['present', 'nullable', 'string', 'max:'.config()->integer('moashir.assessment.review_note_max_chars')],
            'reason' => [Rule::requiredIf($this->actsAsSuperAdmin()), 'nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $seen = [];

            foreach ($this->changesFrom($this->input('notes'))->entries as $index => $change) {
                $key = $change->unit->key();

                if (isset($seen[$key])) {
                    $validator->errors()->add('notes.'.$index, __('validation.distinct', ['attribute' => 'notes.'.$index]));
                }

                $seen[$key] = true;
            }
        }];
    }

    public function changes(): ReviewNoteChanges
    {
        return $this->changesFrom($this->validated('notes'));
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }

    private function changesFrom(mixed $notes): ReviewNoteChanges
    {
        $entries = [];

        foreach (is_array($notes) ? $notes : [] as $entry) {
            $entries[] = $this->changeOf(is_array($entry) ? $entry : []);
        }

        return new ReviewNoteChanges($entries);
    }

    /**
     * @param  array<array-key, mixed>  $entry
     */
    private function changeOf(array $entry): ReviewNoteChange
    {
        $unit = new ReviewNoteUnit(
            ReviewNoteLevel::from(ScalarValue::string($entry['level'] ?? null)),
            ScalarValue::int($entry['main_criterion_id'] ?? null),
            $this->optionalId($entry['sub_criterion_id'] ?? null),
            $this->optionalId($entry['guiding_point_id'] ?? null),
        );

        return new ReviewNoteChange($unit, is_string($entry['body'] ?? null) ? $entry['body'] : null);
    }

    private function optionalId(mixed $value): ?int
    {
        return $value === null ? null : ScalarValue::int($value);
    }

    private function actsAsSuperAdmin(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isSuperAdmin();
    }
}
