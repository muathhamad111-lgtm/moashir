<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Services\Platform\YearlyNumberSequence;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class CertificateNumberGenerator
{
    private const string SERIES = 'certificates.accredited';

    public function __construct(private YearlyNumberSequence $sequence, private Repository $config) {}

    public function next(CarbonInterface $issuedAt): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Certificate numbers are allocated only inside the transaction that issues the certificate.');
        }

        $year = $issuedAt->copy()->setTimezone($this->config->string('app.display_timezone'))->year;

        return sprintf('CRT-%d-%06d', $year, $this->sequence->next(self::SERIES, $year));
    }
}
