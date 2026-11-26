<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Enums\Identity\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

final class RecordOwnGenderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['gender' => ['required', Rule::enum(Gender::class)]];
    }

    public function gender(): Gender
    {
        return $this->enum('gender', Gender::class) ?? throw new LogicException('Gender is validated as required.');
    }
}
