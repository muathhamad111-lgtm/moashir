<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\EntityLookupQuery;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;

final class LookupPrivacyEntitiesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.EntityLookupQuery::MAX_LIMIT],
        ];
    }

    public function lookupQuery(): EntityLookupQuery
    {
        return new EntityLookupQuery(
            SearchTerm::tryFrom($this->string('search')->toString()),
            $this->integer('limit', EntityLookupQuery::DEFAULT_LIMIT),
        );
    }
}
