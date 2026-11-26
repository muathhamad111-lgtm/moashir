<?php

declare(strict_types=1);

namespace App\Data\Nda;

use App\Models\Identity\TermsVersion;
use Carbon\CarbonInterface;

final readonly class NdaOverview
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public ?TermsVersion $current,
        public ?TermsVersion $draft,
        public array $roles,
        public int $reminderDays,
        public ?CarbonInterface $nextManualReminderAt,
    ) {}
}
