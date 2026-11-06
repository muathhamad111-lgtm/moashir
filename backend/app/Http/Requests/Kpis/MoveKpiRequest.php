<?php

declare(strict_types=1);

namespace App\Http\Requests\Kpis;

use App\Enums\Kpis\ReorderDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MoveKpiRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(ReorderDirection::class)],
        ];
    }

    public function direction(): ReorderDirection
    {
        return $this->enum('direction', ReorderDirection::class) ?? ReorderDirection::Up;
    }
}
