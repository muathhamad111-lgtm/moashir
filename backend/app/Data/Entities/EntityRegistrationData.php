<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Identity\Locale;
use App\Support\ValueObjects\EmailAddress;

final readonly class EntityRegistrationData
{
    /**
     * @param  list<int>  $acceptedTermsVersionIds
     */
    public function __construct(
        public EntityProfileData $profile,
        public EntityContactData $contact,
        public EmailAddress $officialEmail,
        public string $password,
        public array $acceptedTermsVersionIds,
        public bool $marketingConsent,
        public Locale $locale,
    ) {}
}
