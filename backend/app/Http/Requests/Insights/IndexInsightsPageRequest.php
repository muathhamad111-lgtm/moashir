<?php

declare(strict_types=1);

namespace App\Http\Requests\Insights;

use Illuminate\Foundation\Http\FormRequest;

final class IndexInsightsPageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['page' => ['nullable', 'integer', 'min:1']];
    }

    public function page(): int
    {
        return $this->integer('page', 1);
    }
}
