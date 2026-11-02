<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\PermanentDeletionInstruction;
use Illuminate\Foundation\Http\FormRequest;

final class PermanentlyDeleteEntityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
            'confirmation' => ['required', 'string', 'max:40'],
            'disclaimer_acknowledged' => ['required', 'boolean'],
        ];
    }

    public function instruction(): PermanentDeletionInstruction
    {
        return new PermanentDeletionInstruction(
            $this->string('reason')->trim()->toString(),
            $this->string('confirmation')->toString(),
            $this->boolean('disclaimer_acknowledged'),
        );
    }
}
