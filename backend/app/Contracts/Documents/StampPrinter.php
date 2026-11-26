<?php

declare(strict_types=1);

namespace App\Contracts\Documents;

use App\Data\Watermark\StampSheet;

interface StampPrinter
{
    public function print(StampSheet $sheet): string;
}
