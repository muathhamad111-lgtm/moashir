<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\CompletionExpiryOutcome;
use App\Enums\Assessments\CompletionItemKind;
use Carbon\CarbonImmutable;

final readonly class CompletionInstruction
{
    /**
     * @param  list<array{sub_criterion_id: int, kind: string, note: string|null}>  $items
     */
    public function __construct(
        public string $notes,
        public array $items,
        public CarbonImmutable $deadlineAt,
        public CompletionExpiryOutcome $expiryOutcome,
        public ?string $reason,
    ) {}

    /**
     * @param  array<array-key, mixed>  $items
     */
    public static function fromInput(string $notes, array $items, string $deadlineAt, string $expiryOutcome, ?string $reason): self
    {
        return new self($notes, self::itemsOf($items), CarbonImmutable::parse($deadlineAt)->utc(), CompletionExpiryOutcome::from($expiryOutcome), $reason);
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return list<array{sub_criterion_id: int, kind: string, note: string|null}>
     */
    private static function itemsOf(array $items): array
    {
        $parsed = [];

        foreach ($items as $item) {
            $item = is_array($item) ? $item : [];
            $parsed[] = [
                'sub_criterion_id' => is_numeric($item['sub_criterion_id'] ?? null) ? (int) $item['sub_criterion_id'] : 0,
                'kind' => is_string($item['kind'] ?? null) ? $item['kind'] : CompletionItemKind::Cv->value,
                'note' => is_string($item['note'] ?? null) && trim($item['note']) !== '' ? trim($item['note']) : null,
            ];
        }

        return $parsed;
    }
}
