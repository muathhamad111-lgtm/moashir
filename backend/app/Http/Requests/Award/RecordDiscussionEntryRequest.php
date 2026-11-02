<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Enums\Award\DiscussionEntryKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordDiscussionEntryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(DiscussionEntryKind::class)],
            'body' => ['nullable', 'required_unless:kind,'.DiscussionEntryKind::Approve->value, 'string', 'min:10', 'max:5000'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function kind(): DiscussionEntryKind
    {
        return DiscussionEntryKind::from($this->string('kind')->toString());
    }

    public function body(): ?string
    {
        return $this->filled('body') ? $this->string('body')->trim()->toString() : null;
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
