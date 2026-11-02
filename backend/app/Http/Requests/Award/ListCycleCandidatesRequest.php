<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Enums\Award\CandidateRole;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListCycleCandidatesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(CandidateRole::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function role(): CandidateRole
    {
        return CandidateRole::from($this->string('role')->toString());
    }

    public function search(): ?SearchTerm
    {
        return SearchTerm::tryFrom($this->string('search')->toString());
    }
}
