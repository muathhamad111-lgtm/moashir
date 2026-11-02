<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\SoftDeletionInstruction;
use App\Support\StringList;
use Illuminate\Foundation\Http\FormRequest;

final class SoftDeleteEntityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm_open_requests' => ['sometimes', 'boolean'],
            'acknowledged_request_numbers' => ['sometimes', 'array', 'max:500'],
            'acknowledged_request_numbers.*' => ['string', 'max:40', 'distinct'],
        ];
    }

    public function instruction(): SoftDeletionInstruction
    {
        return new SoftDeletionInstruction(
            $this->string('reason')->trim()->toString(),
            $this->boolean('confirm_open_requests'),
            StringList::from($this->input('acknowledged_request_numbers', [])),
        );
    }
}
