<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\Entities\ProfileCompletionItem;
use InvalidArgumentException;

final readonly class ProfileCompletion
{
    private const int FULL = 100;

    /**
     * @var array<string, bool>
     */
    private array $completed;

    /**
     * @param  array<string, bool>  $checks
     */
    public function __construct(array $checks)
    {
        $completed = [];

        foreach (ProfileCompletionItem::cases() as $item) {
            $completed[$item->value] = $checks[$item->value] ?? throw new InvalidArgumentException('A profile completion check is missing: '.$item->value.'.');
        }

        $this->completed = $completed;
    }

    public function includes(ProfileCompletionItem $item): bool
    {
        return $this->completed[$item->value];
    }

    public function percent(): int
    {
        $required = ProfileCompletionItem::required();
        $complete = array_filter($required, $this->includes(...));

        return intdiv(self::FULL * count($complete), count($required));
    }

    public function isComplete(): bool
    {
        return $this->percent() === self::FULL;
    }

    /**
     * @return array{percent: int, items: list<array{key: string, is_complete: bool, is_required: bool}>}
     */
    public function toArray(): array
    {
        return [
            'percent' => $this->percent(),
            'items' => array_map(fn (ProfileCompletionItem $item): array => [
                'key' => $item->value,
                'is_complete' => $this->includes($item),
                'is_required' => $item->isRequired(),
            ], ProfileCompletionItem::cases()),
        ];
    }
}
