<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Entities\Entity;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\PersonalDataPackageBuilder;
use App\Support\RequestContext;

final readonly class ExportEntityPersonalData
{
    public function __construct(
        private PersonalDataPackageBuilder $builder,
        private RequestContext $context,
        private AuditRecorder $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Entity $entity, string $reason): array
    {
        $this->context->recordReason($reason);
        $package = $this->builder->build($entity);

        $this->audit->record(new AuditEntry(AuditEvent::PrivacyDataExported, $entity, [], ['format' => 'json'], ['privacy', 'export']));

        return $package;
    }
}
