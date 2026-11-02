<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Models\Criteria\CriteriaVersion;
use Illuminate\Foundation\Http\FormRequest;

final class ShowPublishedCriteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->filled('version') || $this->user()?->can('viewAny', CriteriaVersion::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['version' => ['nullable', 'integer', 'min:1']];
    }

    public function requestedVersion(): ?CriteriaVersion
    {
        return $this->filled('version') ? CriteriaVersion::query()->findOrFail($this->integer('version')) : null;
    }
}
