<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\TransactionSort;
use App\Enums\Finance\TransactionType;
use App\Enums\Integrations\IntegrationMode;
use App\Support\Finance\TransactionStatusFilter;
use App\Support\ScalarValue;
use App\Support\ValueObjects\SearchTerm;
use Carbon\CarbonImmutable;

final readonly class TransactionFilters
{
    /**
     * @param  list<PaymentStatus>  $statuses  an empty list means every status
     * @param  list<TransactionType>  $types  an empty list means every type
     * @param  IntegrationMode|null  $mode  null means both modes: confirmed preview payments count as revenue too
     */
    public function __construct(
        public ?SearchTerm $search = null,
        public array $statuses = [],
        public ?PaymentPurpose $purpose = null,
        public ?PaymentInstrument $method = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?IntegrationMode $mode = null,
        public TransactionSort $sort = TransactionSort::NewestFirst,
        public int $perPage = 15,
        public ?PaymentChannel $channel = null,
        public ?int $entityId = null,
        public array $types = [],
    ) {}

    /**
     * @return array<string, scalar|list<string>|null>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search?->value,
            'status' => array_map(static fn (PaymentStatus $status): string => $status->value, $this->statuses),
            'type' => array_map(static fn (TransactionType $type): string => $type->value, $this->types),
            'purpose' => $this->purpose?->value,
            'method' => $this->method?->value,
            'channel' => $this->channel?->value,
            'entity_id' => $this->entityId,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'mode' => $this->mode?->value,
            'sort' => $this->sort->value,
        ];
    }

    /**
     * Rebuilds the filters an export stored; older exports kept a single status / type string.
     *
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $search = ScalarValue::string($values['search'] ?? null);
        $from = ScalarValue::string($values['from'] ?? null);
        $to = ScalarValue::string($values['to'] ?? null);
        $entityId = ScalarValue::string($values['entity_id'] ?? null);

        return new self(
            $search === '' ? null : new SearchTerm($search),
            TransactionStatusFilter::resolve(self::listOf($values['status'] ?? null)),
            PaymentPurpose::tryFrom(ScalarValue::string($values['purpose'] ?? null)),
            PaymentInstrument::tryFrom(ScalarValue::string($values['method'] ?? null)),
            $from === '' ? null : CarbonImmutable::parse($from),
            $to === '' ? null : CarbonImmutable::parse($to),
            IntegrationMode::tryFrom(ScalarValue::string($values['mode'] ?? null)),
            TransactionSort::tryFrom(ScalarValue::string($values['sort'] ?? null)) ?? TransactionSort::NewestFirst,
            channel: PaymentChannel::tryFrom(ScalarValue::string($values['channel'] ?? null)),
            entityId: ctype_digit($entityId) ? (int) $entityId : null,
            types: TransactionType::resolve(self::listOf($values['type'] ?? null)),
        );
    }

    /**
     * @return list<string>
     */
    private static function listOf(mixed $stored): array
    {
        $values = is_array($stored) ? $stored : [$stored];

        return array_values(array_map(static fn (mixed $value): string => ScalarValue::string($value), $values));
    }
}
