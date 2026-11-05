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

    private const string TEST_SERIES = 'certificates.accredited_test';

    private const string TEST_PREFIX = 'TEST-';

    public function __construct(private YearlyNumberSequence $sequence, private Repository $config) {}

    public function next(CarbonInterface $issuedAt, bool $isTest): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Certificate numbers are allocated only inside the transaction that issues the certificate.');
        }

        $year = $issuedAt->copy()->setTimezone($this->config->string('app.display_timezone'))->year;
        $value = $this->sequence->next($isTest ? self::TEST_SERIES : self::SERIES, $year);

        return sprintf('%sCRT-%d-%06d', $isTest ? self::TEST_PREFIX : '', $year, $value);
    }
}
