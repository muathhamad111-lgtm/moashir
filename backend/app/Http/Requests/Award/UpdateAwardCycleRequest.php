<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

final class UpdateAwardCycleRequest extends CycleAttributesRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->attributeRules('sometimes'),
            'status' => ['prohibited'],
        ];
    }
}
