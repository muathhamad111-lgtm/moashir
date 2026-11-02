<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\Files\FileScanner;
use App\Data\Files\ScanVerdict;

final class FakeFileScanner implements FileScanner
{
    public const string ENGINE = 'fake-scanner';

    public const string TEST_SIGNATURE = 'MOASHIR-FAKE-MALWARE-SIGNATURE';

    private bool $unavailable = false;

    private int $scanned = 0;

    public function scan($stream): ScanVerdict
    {
        $this->scanned++;

        if ($this->unavailable) {
            return ScanVerdict::failed(self::ENGINE);
        }

        $contents = stream_get_contents($stream);

        return is_string($contents) && str_contains($contents, self::TEST_SIGNATURE)
            ? ScanVerdict::infected(self::ENGINE, 'Moashir.Test.Signature')
            : ScanVerdict::clean(self::ENGINE);
    }

    public function makeUnavailable(): void
    {
        $this->unavailable = true;
    }

    public function makeAvailable(): void
    {
        $this->unavailable = false;
    }

    public function scannedCount(): int
    {
        return $this->scanned;
    }
}
