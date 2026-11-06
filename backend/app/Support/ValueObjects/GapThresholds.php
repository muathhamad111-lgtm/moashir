<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\Insights\GapClass;
use App\Support\ScalarValue;
use InvalidArgumentException;

final readonly class GapThresholds
{
    public const array DEFAULTS = ['critical_below' => '50.00', 'strength_from' => '80.00', 'version' => 1];

    private function __construct(public Percent $criticalBelow, public Percent $strengthFrom, public int $version)
    {
        if ($criticalBelow->tenThousandths <= 0 || $criticalBelow->tenThousandths >= $strengthFrom->tenThousandths) {
            throw new InvalidArgumentException('The critical-gap threshold must be above zero and below the strength threshold.');
        }

        if ($version < 1) {
            throw new InvalidArgumentException('A rules version starts at one.');
        }
    }

    public static function of(string $criticalBelow, string $strengthFrom, int $version = 1): self
    {
        return new self(Percent::ofDisplayed($criticalBelow), Percent::ofDisplayed($strengthFrom), $version);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return self::of(
            ScalarValue::string($values['critical_below'] ?? null),
            ScalarValue::string($values['strength_from'] ?? null),
            ScalarValue::int($values['version'] ?? null, 1),
        );
    }

    public static function defaults(): self
    {
        return self::fromArray(self::DEFAULTS);
    }

    public function classify(Percent $percent): GapClass
    {
        $shown = $percent->displayedHundredths();

        return match (true) {
            $shown < $this->criticalBelow->displayedHundredths() => GapClass::CriticalGap,
            $shown >= $this->strengthFrom->displayedHundredths() => GapClass::Strength,
            default => GapClass::Opportunity,
        };
    }

    public function revisedTo(self $proposal): self
    {
        return $this->hasSameBoundsAs($proposal)
            ? $this
            : new self($proposal->criticalBelow, $proposal->strengthFrom, $this->version + 1);
    }

    public function hasSameBoundsAs(self $other): bool
    {
        return $this->criticalBelow->equals($other->criticalBelow) && $this->strengthFrom->equals($other->strengthFrom);
    }

    /**
     * @return array{critical_below: string, strength_from: string, version: int}
     */
    public function toArray(): array
    {
        return [
            'critical_below' => $this->criticalBelow->toDisplay(),
            'strength_from' => $this->strengthFrom->toDisplay(),
            'version' => $this->version,
        ];
    }
}
