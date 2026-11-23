<?php

declare(strict_types=1);

namespace App\Services\Entities\ProfileChanges;

use App\Data\Entities\ProfileChangeDecision;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileChangeFile;
use App\Exceptions\Entities\ProfileChangeRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Files\StoredFile;
use App\Services\Entities\EntityFreezeGuard;

/**
 * The rules a decision must meet before it touches the entity's approved profile.
 */
final readonly class ProfileChangeDecisionGuard
{
    /**
     * Fields that only make sense together: a city belongs to its region, a VAT number to a VAT registration.
     */
    private const array COUPLED = [
        [EntityProfileField::Region, EntityProfileField::City],
        [EntityProfileField::VatRegistered, EntityProfileField::VatNumber],
    ];

    public function __construct(private ProfileChangeValues $values, private EntityFreezeGuard $freezeGuard) {}

    public function assertDecidable(EntityProfileChangeRequest $request, ProfileChangeDecision $decision): void
    {
        if (! $request->isPending()) {
            throw IllegalStateTransition::from($request->status->value, 'decide');
        }

        if ($request->revision !== $decision->revision) {
            throw ProfileChangeRejected::amended($request->revision);
        }

        $this->assertItems($request->items(), $decision);
    }

    public function assertApplicable(EntityProfileChangeRequest $request, Entity $entity, ProfileChangeDecision $decision): void
    {
        $this->freezeGuard->assertNotFrozen($entity);

        $stale = array_values(array_filter($this->staleFields($request, $entity), $decision->approves(...)));

        if ($stale !== []) {
            throw ProfileChangeRejected::stale($stale);
        }

        foreach ($request->fileChanges()->changed() as $file) {
            $pending = $file === ProfileChangeFile::Logo ? $request->pendingLogo : $request->pendingVatCertificate;

            if ($decision->approves($file->value) && $pending instanceof StoredFile && ! $pending->isScannedClean()) {
                throw ProfileChangeRejected::fileNotClean($file->value);
            }
        }
    }

    /**
     * The request's fields whose approved value changed after the entity proposed them; they can only be rejected.
     *
     * @return list<string>
     */
    public function staleFields(EntityProfileChangeRequest $request, Entity $entity): array
    {
        $fields = $request->changed_fields;
        $current = $this->values->approvedOf($entity, $fields);

        return array_values(array_filter($fields, static fn (string $field): bool => $current[$field] !== ($request->base_values[$field] ?? null)));
    }

    /**
     * @param  list<string>  $items
     */
    private function assertItems(array $items, ProfileChangeDecision $decision): void
    {
        if (array_diff($decision->approvedItems, $items) !== []) {
            throw ProfileChangeRejected::unknownItems();
        }

        foreach (self::COUPLED as $group) {
            $present = array_values(array_filter(array_map(static fn (EntityProfileField $field): string => $field->value, $group), static fn (string $field): bool => in_array($field, $items, true)));

            if (count(array_unique(array_map($decision->approves(...), $present))) > 1) {
                throw ProfileChangeRejected::coupledItems();
            }
        }

        if ($decision->rejectsAnyOf($items) && $decision->reason === null) {
            throw ProfileChangeRejected::reasonRequired();
        }
    }
}
