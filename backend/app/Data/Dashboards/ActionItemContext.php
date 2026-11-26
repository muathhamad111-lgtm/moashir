<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

final readonly class ActionItemContext
{
    public function __construct(
        public ?int $assessmentId = null,
        public ?string $paymentId = null,
        public ?string $messageId = null,
        public ?string $reference = null,
        public ?int $remainingSubs = null,
        public ?int $percent = null,
    ) {}

    public static function none(): self
    {
        return new self;
    }

    public static function forAssessment(int $assessmentId, string $reference, ?int $remainingSubs = null): self
    {
        return new self(assessmentId: $assessmentId, reference: $reference, remainingSubs: $remainingSubs);
    }

    public static function forPayment(string $paymentId, string $reference): self
    {
        return new self(paymentId: $paymentId, reference: $reference);
    }

    public static function forMessage(string $messageId, string $reference): self
    {
        return new self(messageId: $messageId, reference: $reference);
    }

    public static function forProfile(int $percent): self
    {
        return new self(percent: $percent);
    }

    /**
     * @return array{assessment_id: int|null, payment_id: string|null, message_id: string|null, reference: string|null, remaining_subs: int|null, percent: int|null}
     */
    public function toArray(): array
    {
        return [
            'assessment_id' => $this->assessmentId,
            'payment_id' => $this->paymentId,
            'message_id' => $this->messageId,
            'reference' => $this->reference,
            'remaining_subs' => $this->remainingSubs,
            'percent' => $this->percent,
        ];
    }
}
