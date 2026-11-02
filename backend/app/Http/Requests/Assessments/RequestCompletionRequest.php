<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Data\Assessments\CompletionInstruction;
use App\Enums\Assessments\CompletionExpiryOutcome;
use App\Enums\Assessments\CompletionItemKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

final class RequestCompletionRequest extends FormRequest
{
    private const int MAX_ITEMS = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $latest = Date::now()->addDays(config()->integer('moashir.assessment.completion_deadline_max_days'))->toIso8601String();

        return [
            'notes' => ['required', 'string', 'min:10', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.sub_criterion_id' => ['required', 'integer', 'min:1'],
            'items.*.kind' => ['required', Rule::enum(CompletionItemKind::class)],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'deadline_at' => ['required', 'date', 'after:now', 'before_or_equal:'.$latest],
            'expiry_outcome' => ['required', Rule::enum(CompletionExpiryOutcome::class)],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function instruction(): CompletionInstruction
    {
        return CompletionInstruction::fromInput(
            $this->string('notes')->trim()->toString(),
            (array) $this->validated('items'),
            $this->string('deadline_at')->toString(),
            $this->string('expiry_outcome')->toString(),
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }
}
