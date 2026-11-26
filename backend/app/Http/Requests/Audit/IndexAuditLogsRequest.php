<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use App\Data\Audit\AuditLogFilters;
use App\Support\Audit\AuditLogFilterInput;
use Illuminate\Foundation\Http\FormRequest;

final class IndexAuditLogsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return AuditLogFilterInput::rules();
    }

    public function filters(): AuditLogFilters
    {
        return AuditLogFilterInput::filters($this->validated());
    }
}
