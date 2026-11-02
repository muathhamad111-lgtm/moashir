<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\Assessment;

final readonly class CvSheet
{
    /**
     * @param  array<string, string>  $bodiesByCode
     */
    public function __construct(public Assessment $assessment, public BoundSubCriteria $subs, public array $bodiesByCode) {}

    /**
     * @return array<int, string>
     */
    public function bodiesBySubId(): array
    {
        $bodies = [];

        foreach ($this->bodiesByCode as $code => $body) {
            $id = $this->subs->idOf($code);

            if ($id !== null) {
                $bodies[$id] = $body;
            }
        }

        return $bodies;
    }
}
