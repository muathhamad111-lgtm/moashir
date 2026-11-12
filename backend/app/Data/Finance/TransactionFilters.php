<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\TransactionSort;
use App\Enums\Integrations\IntegrationMode;
use App\Support\ScalarValue;
use App\Support\ValueObjects\SearchTerm;
use Carbon\CarbonImmutable;

final readonly class TransactionFilters
{
    public function __construct(
        public ?SearchTerm $search = null,
        public ?PaymentStatus $status = null,
        public ?PaymentPurpose $purpose = null,
        public ?PaymentInstrument $method = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public IntegrationMode $mode = IntegrationMode::Live,
        public TransactionSort $sort = TransactionSort::NewestFirst,
        public int $perPage = 15,
        public ?PaymentChannel $channel = null,
        public ?int $entityId = null,
    ) {}

    public function forLiveMode(): self
    {
        return new self($this->search, $this->status, $this->purpose, $this->method, $this->from, $this->to, IntegrationMode::Live, $this->sort, $this->perPage, $this->channel, $this->entityId);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search?->value,
            'status' => $this->status?->value,
            'purpose' => $this->purpose?->value,
            'method' => $this->method?->value,
            'channel' => $this->channel?->value,
            'entity_id' => $this->entityId,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'mode' => $this->mode->value,
            'sort' => $this->sort->value,
        ];
    }

    /**
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
            PaymentStatus::tryFrom(ScalarValue::string($values['status'] ?? null)),
            PaymentPurpose::tryFrom(ScalarValue::string($values['purpose'] ?? null)),
            PaymentInstrument::tryFrom(ScalarValue::string($values['method'] ?? null)),
            $from === '' ? null : CarbonImmutable::parse($from),
            $to === '' ? null : CarbonImmutable::parse($to),
            IntegrationMode::tryFrom(ScalarValue::string($values['mode'] ?? null)) ?? IntegrationMode::Live,
            TransactionSort::tryFrom(ScalarValue::string($values['sort'] ?? null)) ?? TransactionSort::NewestFirst,
            channel: PaymentChannel::tryFrom(ScalarValue::string($values['channel'] ?? null)),
            entityId: ctype_digit($entityId) ? (int) $entityId : null,
        );
    }
}
