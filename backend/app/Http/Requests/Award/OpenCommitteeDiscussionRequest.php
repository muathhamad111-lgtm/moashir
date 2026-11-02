<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Data\Award\DiscussionInstruction;
use App\Enums\Award\CommitteeDiscussionMode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OpenCommitteeDiscussionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(CommitteeDiscussionMode::class)],
            'deadline_at' => ['nullable', 'required_if:mode,'.CommitteeDiscussionMode::Session->value, 'date', 'after:now'],
            'agreed_outcome' => ['nullable', 'required_if:mode,'.CommitteeDiscussionMode::AgreedOutcome->value, 'string', 'min:10', 'max:5000'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function instruction(): DiscussionInstruction
    {
        return new DiscussionInstruction(
            CommitteeDiscussionMode::from($this->string('mode')->toString()),
            $this->filled('deadline_at') ? CarbonImmutable::parse($this->string('deadline_at')->toString()) : null,
            $this->filled('agreed_outcome') ? $this->string('agreed_outcome')->trim()->toString() : null,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }
}
