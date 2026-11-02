<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileData;
use App\Support\Entities\EntityProfileInput;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateEntityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...EntityProfileInput::rules($this->input('region_id')),
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function profile(): EntityProfileData
    {
        return EntityProfileInput::profile($this->validated());
    }

    public function contact(): EntityContactData
    {
        return EntityProfileInput::contact($this->validated());
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }
}
