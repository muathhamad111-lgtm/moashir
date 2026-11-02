<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Data\Award\FilteringInstruction;
use Illuminate\Foundation\Http\FormRequest;

final class QualifyRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'override_reason' => ['nullable', 'string', 'min:20', 'max:1000'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function instruction(): FilteringInstruction
    {
        return new FilteringInstruction(
            $this->filled('note') ? $this->string('note')->trim()->toString() : null,
            $this->filled('override_reason') ? $this->string('override_reason')->trim()->toString() : null,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }
}
