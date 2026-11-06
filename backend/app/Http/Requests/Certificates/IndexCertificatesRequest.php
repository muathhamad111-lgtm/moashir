<?php

declare(strict_types=1);

namespace App\Http\Requests\Certificates;

use App\Data\Certificates\CertificateListFilters;
use App\Enums\Certificates\CertificateLevel;
use App\Enums\Certificates\CertificateStatus;
use App\Rules\EnumValueList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexCertificatesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status,level,entity_id,assessment_id'],
            'filter.status' => ['nullable', new EnumValueList(CertificateStatus::class)],
            'filter.level' => ['nullable', Rule::enum(CertificateLevel::class)],
            'filter.entity_id' => ['nullable', 'integer', 'min:1'],
            'filter.assessment_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): CertificateListFilters
    {
        return new CertificateListFilters(
            EnumValueList::parse($this->string('filter.status')->toString(), CertificateStatus::class),
            CertificateLevel::tryFrom($this->string('filter.level')->toString()),
            $this->filled('filter.entity_id') ? $this->integer('filter.entity_id') : null,
            $this->filled('filter.assessment_id') ? $this->integer('filter.assessment_id') : null,
            $this->filled('search') ? strtoupper($this->string('search')->trim()->toString()) : null,
            $this->integer('per_page', 15),
        );
    }
}
