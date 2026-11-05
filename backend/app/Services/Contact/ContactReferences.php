<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Services\Platform\YearlyNumberSequence;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;

final readonly class ContactReferences
{
    private const string SERIES = 'contact.messages';

    private const string FORMAT = 'CNT-%d-%06d';

    private const int DECOY_MAX = 999_999;

    public function __construct(private YearlyNumberSequence $sequence, private Repository $config) {}

    public function next(CarbonInterface $receivedAt): string
    {
        $year = $this->yearOf($receivedAt);

        return sprintf(self::FORMAT, $year, $this->sequence->next(self::SERIES, $year));
    }

    public function decoy(CarbonInterface $receivedAt): string
    {
        return sprintf(self::FORMAT, $this->yearOf($receivedAt), random_int(1, self::DECOY_MAX));
    }

    private function yearOf(CarbonInterface $moment): int
    {
        return $moment->copy()->setTimezone($this->config->string('app.display_timezone'))->year;
    }
}
