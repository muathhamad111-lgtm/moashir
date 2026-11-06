<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\Insights\MaturityBand;
use App\Support\ScalarValue;
use InvalidArgumentException;

final readonly class MaturityBounds
{
    public const array DEFAULTS = ['developing_from' => '50.00', 'mature_from' => '70.00', 'leading_from' => '80.00', 'version' => 1];

    private function __construct(
        public Percent $developingFrom,
        public Percent $matureFrom,
        public Percent $leadingFrom,
        public int $version,
    ) {
        if ($developingFrom->tenThousandths <= 0
            || $developingFrom->tenThousandths >= $matureFrom->tenThousandths
            || $matureFrom->tenThousandths >= $leadingFrom->tenThousandths) {
            throw new InvalidArgumentException('Maturity band lower bounds must be above zero and strictly ascending.');
        }

        if ($version < 1) {
            throw new InvalidArgumentException('A rules version starts at one.');
        }
    }

    public static function of(string $developingFrom, string $matureFrom, string $leadingFrom, int $version = 1): self
    {
        return new self(Percent::ofDisplayed($developingFrom), Percent::ofDisplayed($matureFrom), Percent::ofDisplayed($leadingFrom), $version);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return self::of(
            ScalarValue::string($values['developing_from'] ?? null),
            ScalarValue::string($values['mature_from'] ?? null),
            ScalarValue::string($values['leading_from'] ?? null),
            ScalarValue::int($values['version'] ?? null, 1),
        );
    }

    public static function defaults(): self
    {
        return self::fromArray(self::DEFAULTS);
    }

    public function bandFor(Percent $percent): MaturityBand
    {
        $shown = $percent->displayedHundredths();

        return match (true) {
            $shown >= $this->leadingFrom->displayedHundredths() => MaturityBand::Leading,
            $shown >= $this->matureFrom->displayedHundredths() => MaturityBand::Mature,
            $shown >= $this->developingFrom->displayedHundredths() => MaturityBand::Developing,
            default => MaturityBand::Emerging,
        };
    }

    public function revisedTo(self $proposal): self
    {
        return $this->hasSameBoundsAs($proposal)
            ? $this
            : new self($proposal->developingFrom, $proposal->matureFrom, $proposal->leadingFrom, $this->version + 1);
    }

    public function hasSameBoundsAs(self $other): bool
    {
        return $this->developingFrom->equals($other->developingFrom)
            && $this->matureFrom->equals($other->matureFrom)
            && $this->leadingFrom->equals($other->leadingFrom);
    }

    /**
     * @return array{developing_from: string, mature_from: string, leading_from: string, version: int}
     */
    public function toArray(): array
    {
        return [
            'developing_from' => $this->developingFrom->toDisplay(),
            'mature_from' => $this->matureFrom->toDisplay(),
            'leading_from' => $this->leadingFrom->toDisplay(),
            'version' => $this->version,
        ];
    }
}
