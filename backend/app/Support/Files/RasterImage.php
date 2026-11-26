<?php

declare(strict_types=1);

namespace App\Support\Files;

final readonly class RasterImage
{
    private const int SIGNATURE_BYTES = 16;

    private const array MIME_TYPES = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg'];

    private const array EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg'];

    private function __construct(
        public string $mimeType,
        public int $width,
        public int $height,
    ) {}

    public static function of(string $contents): ?self
    {
        $size = self::hasRasterSignature($contents) ? self::sizeOf($contents) : null;
        $mimeType = $size === null ? null : (self::MIME_TYPES[$size[2]] ?? null);

        if ($size === null || $mimeType === null || ! ContentSignature::matches($mimeType, substr($contents, 0, self::SIGNATURE_BYTES))) {
            return null;
        }

        return new self($mimeType, $size[0], $size[1]);
    }

    public function hasSidesBetween(int $minimum, int $maximum): bool
    {
        return min($this->width, $this->height) >= $minimum && max($this->width, $this->height) <= $maximum;
    }

    public function extension(): string
    {
        return self::EXTENSIONS[$this->mimeType];
    }

    public function isPng(): bool
    {
        return $this->mimeType === 'image/png';
    }

    private static function hasRasterSignature(string $contents): bool
    {
        $head = substr($contents, 0, self::SIGNATURE_BYTES);

        return strlen($contents) >= self::SIGNATURE_BYTES
            && array_any(self::MIME_TYPES, static fn (string $mimeType): bool => ContentSignature::matches($mimeType, $head));
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function sizeOf(string $contents): ?array
    {
        set_error_handler(static fn (): bool => true);

        try {
            $size = getimagesizefromstring($contents);
        } finally {
            restore_error_handler();
        }

        return $size === false ? null : [$size[0], $size[1], $size[2]];
    }
}
