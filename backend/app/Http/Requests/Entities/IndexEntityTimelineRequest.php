<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Enums\Entities\EntityTimelineScope;
use App\Models\Entities\Entity;
use Illuminate\Foundation\Http\FormRequest;

final class IndexEntityTimelineRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 15;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }

    /**
     * The profile and e-mail changes carry field values, so they follow the profile-history permission
     * (EntityPolicy::viewProfileChanges); everyone who may view the entity sees its lifecycle events.
     */
    public function scopeFor(Entity $entity): EntityTimelineScope
    {
        return $this->user()?->can('viewProfileChanges', $entity) === true ? EntityTimelineScope::Full : EntityTimelineScope::Lifecycle;
    }
}
