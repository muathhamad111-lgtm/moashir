<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Services\Platform\YearlyNumberSequence;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class ReceiptNumberGenerator
{
    private const string SERIES = 'finance.receipt';

    public function __construct(private YearlyNumberSequence $sequence, private Repository $config) {}

    public function next(CarbonInterface $issuedAt): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Receipt numbers are allocated only inside the transaction that issues the receipt.');
        }

        $year = $issuedAt->setTimezone($this->config->string('app.display_timezone'))->year;

        return sprintf('RCP-%d-%06d', $year, $this->sequence->next(self::SERIES, $year));
    }
}
