<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\CityData;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array:ar,en'],
            'name.ar' => ['required', 'string', 'max:120'],
            'name.en' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ];
    }

    public function city(): CityData
    {
        return new CityData(
            new LocalizedText($this->string('name.ar')->trim()->toString(), $this->string('name.en')->trim()->toString()),
            null,
            $this->filled('sort_order') ? $this->integer('sort_order') : null,
        );
    }
}
