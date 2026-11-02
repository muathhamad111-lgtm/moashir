<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use Illuminate\Foundation\Http\FormRequest;

final class ToggleCriteriaNodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['active' => ['required', 'boolean']];
    }

    public function isActive(): bool
    {
        return $this->boolean('active');
    }
}
