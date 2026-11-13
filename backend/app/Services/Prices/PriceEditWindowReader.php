<?php

declare(strict_types=1);

namespace App\Services\Prices;

use App\Data\Prices\PriceEditWindow;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;

final readonly class PriceEditWindowReader
{
    public function __construct(private Repository $config) {}

    public function current(): PriceEditWindow
    {
        return PriceEditWindow::at(CarbonImmutable::instance(Date::now()), $this->config->string('app.display_timezone'));
    }
}
