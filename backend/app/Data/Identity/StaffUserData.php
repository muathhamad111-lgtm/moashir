<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\Gender;
use App\Enums\Identity\Locale;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\NationalId;
use App\Support\ValueObjects\PhoneNumber;

final readonly class StaffUserData
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $name,
        public EmailAddress $email,
        public ?PhoneNumber $phone,
        public array $roles,
        public Locale $locale,
        public Gender $gender,
        public ?NationalId $nationalId = null,
    ) {}
}
