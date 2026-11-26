<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\EntityProfileWrite;
use App\Data\Entities\ProfileSectionUpdate;
use App\Enums\Entities\ProfileSection;
use Illuminate\Foundation\Http\FormRequest;

abstract class ProfileSectionRequest extends FormRequest
{
    private const int MAX_REVISION_LENGTH = 64;

    /**
     * @return array<string, mixed>
     */
    final public function rules(): array
    {
        $rules = ['base_revision' => ['required', 'string', 'max:'.self::MAX_REVISION_LENGTH]];

        foreach ($this->sectionRules() as $field => $fieldRules) {
            $rules[$field] = ['present', ...$fieldRules];
        }

        foreach ($this->prohibitedFields() as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    final public function update(): ProfileSectionUpdate
    {
        return new ProfileSectionUpdate($this->section(), $this->string('base_revision')->toString(), $this->write($this->validated()));
    }

    abstract public function section(): ProfileSection;

    /**
     * @return array<string, list<mixed>>
     */
    abstract protected function sectionRules(): array;

    /**
     * @param  array<array-key, mixed>  $validated
     */
    abstract protected function write(array $validated): EntityProfileWrite;

    /**
     * @return list<string>
     */
    protected function prohibitedFields(): array
    {
        return [];
    }
}
