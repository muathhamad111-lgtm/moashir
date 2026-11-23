<?php

declare(strict_types=1);

namespace App\Http\Requests\Nda;

use Illuminate\Foundation\Http\FormRequest;

final class AcceptNdaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'terms_version_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function termsVersionId(): int
    {
        return $this->integer('terms_version_id');
    }
}
