<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\Certificates\CertificateLevel;
use InvalidArgumentException;

final readonly class LevelThresholds
{
    /**
     * @param  array<string, Percent>  $minimums
     */
    private function __construct(private array $minimums) {}

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $minimums = [];
        $previous = 0;

        foreach (CertificateLevel::ascending() as $level) {
            $raw = $values[$level->value] ?? null;
            $minimum = is_string($raw) ? Percent::of($raw) : throw new InvalidArgumentException('Every certificate level needs a minimum percentage.');

            if ($minimum->tenThousandths <= $previous) {
                throw new InvalidArgumentException('Certificate level minimums must be greater than zero and strictly ascending.');
            }

            $minimums[$level->value] = $minimum;
            $previous = $minimum->tenThousandths;
        }

        return new self($minimums);
    }

    public static function defaults(): self
    {
        return self::fromArray(CertificateLevel::DEFAULT_THRESHOLDS);
    }

    public function levelFor(Percent $result): ?CertificateLevel
    {
        $shown = Percent::of($result->toDisplay());
        $reached = null;

        foreach (CertificateLevel::ascending() as $level) {
            if ($shown->tenThousandths >= $this->minimums[$level->value]->tenThousandths) {
                $reached = $level;
            }
        }

        return $reached;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_map(static fn (Percent $minimum): string => $minimum->toDisplay(), $this->minimums);
    }
}
