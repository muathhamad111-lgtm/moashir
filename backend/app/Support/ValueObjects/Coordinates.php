<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

/**
 * A point on the map in decimal degrees (WGS 84).
 */
final readonly class Coordinates
{
    private const float MAX_LATITUDE = 90.0;

    private const float MAX_LONGITUDE = 180.0;

    public function __construct(public float $latitude, public float $longitude)
    {
        if (abs($latitude) > self::MAX_LATITUDE || abs($longitude) > self::MAX_LONGITUDE) {
            throw new InvalidArgumentException('Coordinates must lie within the valid latitude and longitude ranges.');
        }
    }
}
