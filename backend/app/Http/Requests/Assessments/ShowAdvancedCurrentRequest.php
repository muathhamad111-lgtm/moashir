<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use Illuminate\Foundation\Http\FormRequest;

final class ShowAdvancedCurrentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['award_cycle_id' => ['nullable', 'integer', 'min:1']];
    }

    public function cycleId(): ?int
    {
        return $this->filled('award_cycle_id') ? $this->integer('award_cycle_id') : null;
    }
}
