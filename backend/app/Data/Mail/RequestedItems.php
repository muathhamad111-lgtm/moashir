<?php

declare(strict_types=1);

namespace App\Data\Mail;

/**
 * The items list of a completion e-mail. Only the first {@see self::SHOWN} items are written into the e-mail so it
 * stays short; the rest are counted and left to the platform, which lists them all.
 */
final readonly class RequestedItems
{
    public const int SHOWN = 10;

    /**
     * @param  list<RequestedItem>  $items
     */
    public function __construct(public array $items) {}

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return list<RequestedItem>
     */
    public function shown(): array
    {
        return array_slice($this->items, 0, self::SHOWN);
    }

    public function hiddenCount(): int
    {
        return max(0, $this->count() - self::SHOWN);
    }
}
