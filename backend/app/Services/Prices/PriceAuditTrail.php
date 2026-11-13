<?php

declare(strict_types=1);

namespace App\Services\Prices;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Finance\ServicePriceVersion;
use App\Services\Audit\AuditRecorder;

final readonly class PriceAuditTrail
{
    public function __construct(private AuditRecorder $audit) {}

    public function recordChange(ServicePriceVersion $created, ?ServicePriceVersion $inForce, ?ServicePriceVersion $cancelled): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::PricesSettingsUpdated, $created, [
            'purpose' => $created->purpose->value,
            'in_force' => $this->stateOf($inForce),
            'scheduled' => $this->stateOf($cancelled),
        ], [
            'purpose' => $created->purpose->value,
            'version' => $this->stateOf($created),
        ]));
    }

    public function recordCancellation(ServicePriceVersion $cancelled): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::PricesSettingsUpdated, $cancelled, [
            'purpose' => $cancelled->purpose->value,
            'scheduled' => $this->stateOf($cancelled),
        ], [
            'purpose' => $cancelled->purpose->value,
            'scheduled' => null,
        ]));
    }

    /**
     * @return array{id: int, price_before_vat: string, availability: string, effective_from: string}|null
     */
    private function stateOf(?ServicePriceVersion $version): ?array
    {
        return $version instanceof ServicePriceVersion ? [
            'id' => $version->id,
            'price_before_vat' => $version->price_before_vat,
            'availability' => $version->availability->value,
            'effective_from' => $version->effective_from->toIso8601String(),
        ] : null;
    }
}
