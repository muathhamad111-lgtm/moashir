<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

final class SenderFollowUpRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:2', 'max:5000', 'not_regex:/\x00/']];
    }

    public function body(): string
    {
        return $this->string('body')->trim()->toString();
    }
}
