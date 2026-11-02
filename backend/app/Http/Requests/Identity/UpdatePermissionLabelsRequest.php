<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Support\ValueObjects\LocalizedText;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePermissionLabelsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'array:ar,en'],
            'label.ar' => ['required', 'string', 'min:2', 'max:120'],
            'label.en' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'array:ar,en'],
            'description.ar' => ['required_with:description', 'string', 'max:500'],
            'description.en' => ['required_with:description', 'string', 'max:500'],
        ];
    }

    public function label(): LocalizedText
    {
        return LocalizedText::fromArray((array) $this->input('label'));
    }

    public function description(): ?LocalizedText
    {
        return $this->filled('description') ? LocalizedText::fromArray((array) $this->input('description')) : null;
    }
}
