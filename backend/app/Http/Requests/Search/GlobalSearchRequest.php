<?php

declare(strict_types=1);

namespace App\Http\Requests\Search;

use App\Data\Search\SearchQuery;
use App\Rules\SearchableTerm;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;

final class GlobalSearchRequest extends FormRequest
{
    public const int MIN_TERM_LENGTH = 2;

    public const int MAX_TERM_LENGTH = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:'.self::MAX_TERM_LENGTH, new SearchableTerm(self::MIN_TERM_LENGTH)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.SearchQuery::MAX_LIMIT],
        ];
    }

    public function searchQuery(): SearchQuery
    {
        return new SearchQuery(
            new SearchTerm($this->string('q')->toString()),
            $this->integer('limit', SearchQuery::DEFAULT_LIMIT),
        );
    }
}
