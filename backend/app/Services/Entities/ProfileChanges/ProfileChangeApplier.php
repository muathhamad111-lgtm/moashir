<?php

declare(strict_types=1);

namespace App\Services\Entities\ProfileChanges;

use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileChangeSet;
use App\Data\Entities\EntityProfileWrite;
use App\Data\Entities\ProfileChangeDecision;
use App\Enums\Entities\EntityProfileField;
use App\Exceptions\Entities\VatCertificateRejected;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Services\Entities\EntityProfileWriter;
use App\Support\ScalarValue;
use App\Support\ValueObjects\PhoneNumber;
use Carbon\CarbonImmutable;

/**
 * Writes the approved items of a decided request to the entity's profile, through the same writer as staff edits so
 * history, VAT detachment and profile completeness stay in one place; rejected pending files are discarded.
 */
final readonly class ProfileChangeApplier
{
    public function __construct(
        private EntityProfileWriter $writer,
        private ProfileChangeFileSwap $files,
        private ProfileChangeFileDiscarder $discarder,
    ) {}

    public function apply(EntityProfileChangeRequest $request, Entity $entity, ProfileChangeDecision $decision): EntityProfileChangeSet
    {
        $changes = $this->writer->write($entity, $this->writeOf($request, $entity, $decision), $request->submitter);

        $files = $request->fileChanges();

        foreach ($files->changed() as $file) {
            if ($decision->approves($file->value)) {
                $this->files->apply($entity, $files, $file);
            } else {
                $this->discarder->discard([$files->pendingId($file)]);
            }
        }

        if (! $entity->isVatRegistered() && $entity->vat_certificate_file_id !== null) {
            throw VatCertificateRejected::notRegistered();
        }

        $entity->syncProfileCompletedAt();
        $entity->save();

        return $changes;
    }

    private function writeOf(EntityProfileChangeRequest $request, Entity $entity, ProfileChangeDecision $decision): EntityProfileWrite
    {
        $approved = array_filter($request->proposed, $decision->approves(...), ARRAY_FILTER_USE_KEY);
        $contactFields = array_flip([EntityProfileField::AuthorizedPersonName->value, EntityProfileField::Phone->value]);

        return new EntityProfileWrite(
            $this->withNativeDates(array_diff_key($approved, $contactFields)),
            $this->contactOf($entity, array_intersect_key($approved, $contactFields)),
            null,
            $request->id,
        );
    }

    /**
     * The stored founding date is a plain string; the writer derives the founding year from a date.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withNativeDates(array $attributes): array
    {
        $foundedOn = $attributes[EntityProfileField::FoundedOn->value] ?? null;

        if (is_string($foundedOn)) {
            $attributes[EntityProfileField::FoundedOn->value] = CarbonImmutable::parse($foundedOn);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $approved
     */
    private function contactOf(Entity $entity, array $approved): ?EntityContactData
    {
        if ($approved === []) {
            return null;
        }

        $owner = $entity->owner;
        $name = $approved[EntityProfileField::AuthorizedPersonName->value] ?? $owner?->name;
        $phone = array_key_exists(EntityProfileField::Phone->value, $approved) ? $approved[EntityProfileField::Phone->value] : $owner?->phone;

        return new EntityContactData(ScalarValue::string($name), is_string($phone) ? PhoneNumber::tryFromSaudiInput($phone) : null);
    }
}
