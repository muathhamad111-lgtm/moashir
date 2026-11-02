<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Services\Platform\YearlyNumberSequence;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;

final readonly class PaymentReferenceAllocator
{
    private const string SERIES = 'finance.payment';

    public function __construct(private YearlyNumberSequence $sequence, private Repository $config) {}

    public function next(): string
    {
        $year = Date::now($this->config->string('app.display_timezone'))->year;

        return sprintf('PAY-%d-%06d', $year, $this->sequence->next(self::SERIES, $year));
    }
}
