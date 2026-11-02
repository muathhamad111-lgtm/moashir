<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Data\Award\FilteringInstruction;
use Illuminate\Foundation\Http\FormRequest;

final class DisqualifyRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function instruction(): FilteringInstruction
    {
        return new FilteringInstruction(
            $this->filled('note') ? $this->string('note')->trim()->toString() : null,
            $this->string('reason')->trim()->toString(),
        );
    }
}
