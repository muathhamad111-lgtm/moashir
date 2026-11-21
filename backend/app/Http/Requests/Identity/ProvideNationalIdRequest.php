<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Rules\SaudiNationalId;
use App\Support\ValueObjects\NationalId;
use Illuminate\Foundation\Http\FormRequest;

final class ProvideNationalIdRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['national_id' => ['required', 'string', 'max:20', new SaudiNationalId]];
    }

    public function nationalId(): NationalId
    {
        return new NationalId($this->string('national_id')->toString());
    }
}
