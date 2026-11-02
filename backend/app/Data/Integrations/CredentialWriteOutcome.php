<?php

declare(strict_types=1);

namespace App\Data\Integrations;

final readonly class CredentialWriteOutcome
{
    /**
     * @param  list<string>  $replacedSecrets
     */
    public function __construct(public bool $changed, public array $replacedSecrets = []) {}

    public static function unchanged(): self
    {
        return new self(false);
    }
}
