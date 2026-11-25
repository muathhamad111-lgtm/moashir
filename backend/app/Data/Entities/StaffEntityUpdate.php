<?php

declare(strict_types=1);

namespace App\Data\Entities;

use Illuminate\Http\UploadedFile;

/**
 * A staff edit of the entity's core data on its behalf, with the reason it is made for and the new authorization
 * letter that comes with a new authorized person.
 */
final readonly class StaffEntityUpdate
{
    public function __construct(
        public EntityProfileData $profile,
        public EntityContactData $contact,
        public string $reason,
        public ?UploadedFile $authorizationLetter = null,
    ) {}
}
