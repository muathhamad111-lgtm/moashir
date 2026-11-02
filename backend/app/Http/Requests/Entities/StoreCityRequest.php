<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\CityData;
use App\Models\Entities\City;
use App\Models\Entities\Region;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $region = $this->route('region');

        return [
            'code' => [
                'nullable', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/',
                Rule::unique(City::class, 'code')->where('region_id', $region instanceof Region ? $region->id : 0),
            ],
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
            $this->filled('code') ? $this->string('code')->toString() : null,
            $this->filled('sort_order') ? $this->integer('sort_order') : null,
        );
    }
}
