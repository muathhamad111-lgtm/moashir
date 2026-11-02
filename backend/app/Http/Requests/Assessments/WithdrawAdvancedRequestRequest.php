<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use Illuminate\Foundation\Http\FormRequest;

final class WithdrawAdvancedRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:1000']];
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }
}
