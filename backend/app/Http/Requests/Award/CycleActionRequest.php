<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use Illuminate\Foundation\Http\FormRequest;

final class CycleActionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'min:10', 'max:1000']];
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
