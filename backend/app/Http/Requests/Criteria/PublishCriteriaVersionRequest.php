<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Data\Criteria\PublishInstruction;
use App\Enums\Criteria\CriteriaEditScope;
use App\Enums\Criteria\DraftCyclesChoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PublishCriteriaVersionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirm' => ['accepted'],
            'scope' => ['nullable', Rule::enum(CriteriaEditScope::class)],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
            'draft_cycles' => ['nullable', Rule::enum(DraftCyclesChoice::class)],
        ];
    }

    public function instruction(): PublishInstruction
    {
        return new PublishInstruction(
            $this->enum('scope', CriteriaEditScope::class),
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
            $this->enum('draft_cycles', DraftCyclesChoice::class) ?? DraftCyclesChoice::Keep,
        );
    }
}
