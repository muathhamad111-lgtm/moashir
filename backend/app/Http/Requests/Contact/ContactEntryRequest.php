<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use App\Data\Contact\ContactAction;
use Illuminate\Foundation\Http\FormRequest;

final class ContactEntryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:5000', 'not_regex:/\x00/'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function action(): ContactAction
    {
        return new ContactAction(
            $this->string('body')->trim()->toString(),
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }
}
