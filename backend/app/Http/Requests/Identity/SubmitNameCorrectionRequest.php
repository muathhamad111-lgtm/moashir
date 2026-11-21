<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Data\Identity\IdentityChangeDraft;
use Illuminate\Foundation\Http\FormRequest;

final class SubmitNameCorrectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'new_name' => ['required', 'string', 'min:2', 'max:150'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function draft(): IdentityChangeDraft
    {
        return new IdentityChangeDraft($this->string('new_name')->trim()->toString(), null, $this->string('reason')->trim()->toString());
    }
}
