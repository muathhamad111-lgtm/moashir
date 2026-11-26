<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboards;

use App\Data\Dashboards\BoardFilter;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use Illuminate\Validation\Rule;

final class ExecutiveOverviewRequest extends ReportingPeriodRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'region' => ['nullable', 'string', Rule::exists('regions', 'code')],
            'path' => ['nullable', 'string', Rule::enum(AssessmentPath::class)],
            'type' => ['nullable', 'string', Rule::enum(EntityType::class)],
            'sector' => ['nullable', 'string', Rule::enum(DevelopmentSector::class)],
            'size' => ['nullable', 'string', Rule::enum(OrgSize::class)],
        ];
    }

    public function filter(): BoardFilter
    {
        return new BoardFilter(
            region: $this->filled('region') ? $this->string('region')->toString() : null,
            path: $this->enum('path', AssessmentPath::class),
            type: $this->enum('type', EntityType::class),
            sector: $this->enum('sector', DevelopmentSector::class),
            size: $this->enum('size', OrgSize::class),
        );
    }
}
