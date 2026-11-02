<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Enums\Criteria\ScaleKind;
use Illuminate\Contracts\Config\Repository;

final readonly class CriteriaSettings
{
    public function __construct(private Repository $config) {}

    public function scaleKind(): ScaleKind
    {
        $configured = $this->config->get('moashir.assessment.scale_kind');

        return is_string($configured) ? ScaleKind::tryFrom($configured) ?? ScaleKind::OutOfTen : ScaleKind::OutOfTen;
    }
}
