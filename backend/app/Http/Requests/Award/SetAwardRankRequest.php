<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use Illuminate\Foundation\Http\FormRequest;

final class SetAwardRankRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rank' => ['present', 'nullable', 'integer', 'between:1,3'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function rank(): ?int
    {
        return $this->filled('rank') ? $this->integer('rank') : null;
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
