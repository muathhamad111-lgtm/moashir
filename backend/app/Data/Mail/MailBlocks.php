<?php

declare(strict_types=1);

namespace App\Data\Mail;

/**
 * The optional blocks a notice e-mail adds to its text: the items a completion request lists, the steps of the
 * request and the certificate seal. Each is null when the notice has none.
 */
final readonly class MailBlocks
{
    public function __construct(
        public ?RequestedItems $items = null,
        public ?AccreditedProgress $progress = null,
        public ?CertificateSeal $seal = null,
    ) {}

    public function withItems(RequestedItems $items): self
    {
        return new self($items, $this->progress, $this->seal);
    }

    public function withProgress(AccreditedProgress $progress): self
    {
        return new self($this->items, $progress, $this->seal);
    }

    public function withSeal(CertificateSeal $seal): self
    {
        return new self($this->items, $this->progress, $seal);
    }

    public function listedItems(): ?RequestedItems
    {
        return $this->items?->isEmpty() === false ? $this->items : null;
    }
}
