<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use App\Data\Audit\AuditLogFilters;
use App\Support\Audit\AuditLogFilterInput;
use Illuminate\Foundation\Http\FormRequest;

final class ExportAuditLogsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...AuditLogFilterInput::rules(),
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function filters(): AuditLogFilters
    {
        return AuditLogFilterInput::filters($this->validated());
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
