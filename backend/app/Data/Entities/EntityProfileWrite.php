<?php

declare(strict_types=1);

namespace App\Data\Entities;

final readonly class EntityProfileWrite
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
        public ?EntityContactData $contact = null,
        public ?bool $marketingConsent = null,
    ) {}
}
