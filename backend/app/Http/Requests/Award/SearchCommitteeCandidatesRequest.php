<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;

final class SearchCommitteeCandidatesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100']];
    }

    public function search(): ?SearchTerm
    {
        return SearchTerm::tryFrom($this->string('search')->toString());
    }
}
