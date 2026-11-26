<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Data\Identity\IdentityChangeDraft;
use App\Rules\SaudiNationalId;
use App\Support\ValueObjects\NationalId;
use Illuminate\Foundation\Http\FormRequest;

final class SubmitOwnIdentityChangeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'new_name' => ['nullable', 'required_without:new_national_id', 'string', 'min:2', 'max:150'],
            'new_national_id' => ['nullable', 'required_without:new_name', 'string', new SaudiNationalId],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'current_password' => ['nullable', 'required_with:new_national_id', 'string', 'current_password:web'],
        ];
    }

    public function draft(): IdentityChangeDraft
    {
        return new IdentityChangeDraft(
            $this->filled('new_name') ? $this->string('new_name')->trim()->toString() : null,
            $this->filled('new_national_id') ? new NationalId($this->string('new_national_id')->toString()) : null,
            $this->string('reason')->trim()->toString(),
        );
    }
}
