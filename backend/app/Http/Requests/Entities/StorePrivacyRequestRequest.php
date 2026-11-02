<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\PrivacyRequestData;
use App\Enums\Entities\PrivacyRequestKind;
use App\Models\Entities\Entity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePrivacyRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entity_id' => ['required', 'integer', Rule::exists(Entity::class, 'id')->whereNull('anonymized_at')],
            'kind' => ['required', Rule::enum(PrivacyRequestKind::class)],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function privacyRequest(): PrivacyRequestData
    {
        return new PrivacyRequestData(
            $this->integer('entity_id'),
            PrivacyRequestKind::from($this->string('kind')->toString()),
            $this->string('details')->trim()->toString(),
        );
    }
}
