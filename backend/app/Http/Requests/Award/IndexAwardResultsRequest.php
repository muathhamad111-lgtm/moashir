<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use Illuminate\Foundation\Http\FormRequest;

final class IndexAwardResultsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:region_id'],
            'filter.region_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function regionId(): ?int
    {
        return $this->filled('filter.region_id') ? $this->integer('filter.region_id') : null;
    }
}
