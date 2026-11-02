<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum ResultSnapshotKind: string
{
    case SelfResult = 'self_result';
    case AccreditedResult = 'accredited_result';
    case OverrideRecompute = 'override_recompute';
    case InitialAssessment = 'initial_assessment';
}
