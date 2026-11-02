<?php

declare(strict_types=1);

namespace App\Casts;

use App\Contracts\Assessments\AssessmentState;
use App\Enums\Assessments\AssessmentPath;
use App\Support\ScalarValue;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<AssessmentState, AssessmentState>
 */
final class AssessmentStateCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): AssessmentState
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('The [%s] assessment state is missing.', $key));
        }

        return AssessmentPath::from(ScalarValue::string($attributes['path'] ?? null))->stateFrom($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! $value instanceof AssessmentState) {
            throw new InvalidArgumentException('An assessment state must be set with a state of its path.');
        }

        return (string) $value->value;
    }
}
