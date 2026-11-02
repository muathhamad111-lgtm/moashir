<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Services\Platform\YearlyNumberSequence;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;

final readonly class RequestNumberAllocator
{
    public function __construct(private YearlyNumberSequence $sequence, private Repository $config) {}

    public function next(AssessmentPath $path): string
    {
        $year = Date::now($this->config->string('app.display_timezone'))->year;
        $number = $this->sequence->next('assessment.'.$path->value, $year);

        return sprintf('%s-%d-%06d', $path->numberPrefix(), $year, $number);
    }
}
