<?php

declare(strict_types=1);

namespace App\Integrations\Documents;

use App\Contracts\Documents\StampPrinter;
use App\Data\Watermark\StampSheet;
use App\Exceptions\Documents\DocumentRenderingUnavailable;

final class UnavailableStampPrinter implements StampPrinter
{
    public function print(StampSheet $sheet): string
    {
        throw DocumentRenderingUnavailable::noRenderer();
    }
}
