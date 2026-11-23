<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\ProfileChangeDecision;
use Illuminate\Foundation\Http\FormRequest;

final class DecideProfileChangeRequest extends FormRequest
{
    private const int MAX_ITEMS = 40;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1'],
            'approved_items' => ['present', 'array', 'max:'.self::MAX_ITEMS],
            'approved_items.*' => ['string', 'distinct', 'max:60'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function decision(): ProfileChangeDecision
    {
        /** @var list<string> $approved */
        $approved = array_values($this->array('approved_items'));

        return new ProfileChangeDecision(
            $this->integer('revision'),
            $approved,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }
}
