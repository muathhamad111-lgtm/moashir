<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use App\Enums\Contact\ContactMessageStatus;
use App\Rules\EnumValueList;
use Illuminate\Foundation\Http\FormRequest;

final class IndexOwnContactMessagesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', new EnumValueList(ContactMessageStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return list<ContactMessageStatus>
     */
    public function statuses(): array
    {
        return EnumValueList::parse($this->string('filter.status')->toString(), ContactMessageStatus::class);
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
