<?php

declare(strict_types=1);

namespace App\Services\Entities\ProfileChanges;

use App\Data\Entities\ProfileChangeDecision;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileChangeFile;
use App\Enums\Entities\ProfileSection;
use App\Exceptions\Entities\ProfileChangeRejected;
use App\Models\Entities\EntityProfileChangeRequest;

/**
 * A new authorized person comes with a new authorization letter in the same change request, and the two are
 * approved or rejected together (operator decision, 2026-11-25).
 */
final readonly class AuthorizationLetterRequirement
{
    private const EntityProfileField PERSON = EntityProfileField::AuthorizedPersonName;

    private const ProfileChangeFile LETTER = ProfileChangeFile::AuthorizationLetter;

    /**
     * Checked when the entity saves the section of the authorized person, so an older request waiting without a letter
     * does not block edits to other sections.
     */
    public function assertProposable(EntityProfileChangeRequest $request, ProfileSection $section): void
    {
        if (! in_array(self::PERSON, $section->historyFields(), true)) {
            return;
        }

        if (in_array(self::PERSON->value, $request->changed_fields, true) && $request->fileChanges()->pendingId(self::LETTER) === null) {
            throw ProfileChangeRejected::authorizationLetterRequired();
        }
    }

    /**
     * @param  list<string>  $items
     */
    public function assertDecision(array $items, ProfileChangeDecision $decision): void
    {
        if (! in_array(self::PERSON->value, $items, true)) {
            return;
        }

        $letterApproved = in_array(self::LETTER->value, $items, true) && $decision->approves(self::LETTER->value);

        if ($decision->approves(self::PERSON->value) !== $letterApproved) {
            throw ProfileChangeRejected::authorizationLetterRequired();
        }
    }
}
