<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use App\Data\Contact\ContactSettingsUpdate;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateContactSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'retention_months' => [
                'required',
                'integer',
                'min:'.config()->integer('moashir.contact.retention_months_min'),
                'max:'.config()->integer('moashir.contact.retention_months_max'),
            ],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function settingsUpdate(): ContactSettingsUpdate
    {
        return new ContactSettingsUpdate(
            $this->integer('retention_months'),
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }
}
