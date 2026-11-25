<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\StaffEntityUpdate;
use App\Enums\Entities\EntityDocument;
use App\Models\Entities\Entity;
use App\Services\Entities\StaffAuthorizationLetter;
use App\Support\Entities\EntityProfileInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class UpdateEntityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...EntityProfileInput::rules($this->input('region_id')),
            ...EntityProfileInput::registryRules($this->entityId()),
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            StaffAuthorizationLetter::FIELD => [
                'nullable',
                'file',
                'extensions:pdf',
                'mimes:pdf',
                'max:'.config()->integer(EntityDocument::AuthorizationLetter->maxKilobytesKey()),
            ],
        ];
    }

    public function update(): StaffEntityUpdate
    {
        $validated = $this->validated();
        $letter = $this->file(StaffAuthorizationLetter::FIELD);

        return new StaffEntityUpdate(
            EntityProfileInput::profile($validated),
            EntityProfileInput::contact($validated),
            $this->string('reason')->trim()->toString(),
            $letter instanceof UploadedFile ? $letter : null,
        );
    }

    private function entityId(): ?int
    {
        $entity = $this->route('entity');

        return $entity instanceof Entity ? $entity->id : null;
    }
}
