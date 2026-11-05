<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboards;

use App\Enums\Dashboards\DashboardBoard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DashboardSummaryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'board' => ['nullable', 'string', Rule::enum(DashboardBoard::class)],
        ];
    }

    public function board(): ?DashboardBoard
    {
        return $this->enum('board', DashboardBoard::class);
    }
}
