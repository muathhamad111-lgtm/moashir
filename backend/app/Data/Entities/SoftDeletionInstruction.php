<?php

declare(strict_types=1);

namespace App\Data\Entities;

final readonly class SoftDeletionInstruction
{
    /**
     * @param  list<string>  $acknowledgedRequestNumbers
     */
    public function __construct(
        public string $reason,
        public bool $confirmOpenRequests = false,
        public array $acknowledgedRequestNumbers = [],
    ) {}

    /**
     * @param  list<string>  $openRequestNumbers
     */
    public function acknowledges(array $openRequestNumbers): bool
    {
        if ($openRequestNumbers === []) {
            return true;
        }

        $acknowledged = array_values(array_unique($this->acknowledgedRequestNumbers));
        $open = array_values(array_unique($openRequestNumbers));
        sort($acknowledged);
        sort($open);

        return $this->confirmOpenRequests && $acknowledged === $open;
    }
}
