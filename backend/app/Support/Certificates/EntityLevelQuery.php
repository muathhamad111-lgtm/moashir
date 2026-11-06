<?php

declare(strict_types=1);

namespace App\Support\Certificates;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Certificates\EntityLevel;
use App\Enums\Certificates\EntityLevelFilter;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class EntityLevelQuery
{
    public const string RELATION = 'levelCertificate:id,entity_id,level,number';

    public const string OPEN_ACCREDITED_REQUEST = 'has_open_accredited_request';

    public const string LEVEL_KEY = 'level_key';

    private const string LEVEL_RANK = 'level_rank';

    private const string LEVEL_KEY_EXPRESSION = "coalesce(certificates.level, 'participation')";

    private const string RANK_EXPRESSION = "case when certificates.level is null then 1 when certificates.level = 'bronze' then 2 "
        ."when certificates.level = 'silver' then 3 when certificates.level = 'gold' then 4 when certificates.level = 'diamond' then 5 end";

    /**
     * @return array<string, Closure(Builder<Assessment>): void>
     */
    public static function openAccreditedRequestFlag(): array
    {
        return ['assessments as '.self::OPEN_ACCREDITED_REQUEST => static function (Builder $assessments): void {
            $assessments->where('path', AssessmentPath::Accredited)->whereIn('status', AccreditedAssessmentStatus::underwayValues());
        }];
    }

    /**
     * @param  Builder<Entity>  $query
     */
    public static function filter(Builder $query, EntityLevelFilter $filter): void
    {
        $level = $filter->level();

        if (! $level instanceof EntityLevel) {
            $query->whereDoesntHave('levelCertificate');

            return;
        }

        $query->whereHas('levelCertificate', static function (Builder $certificate) use ($level): void {
            if ($level === EntityLevel::Participation) {
                $certificate->whereNull('level');

                return;
            }

            $certificate->where('level', $level->value);
        });
    }

    /**
     * @param  Builder<Entity>  $query
     */
    public static function orderBy(Builder $query, string $direction): void
    {
        $query->withAggregate('levelCertificate as '.self::LEVEL_RANK, DB::raw(self::RANK_EXPRESSION));
        $query->orderByRaw(self::LEVEL_RANK.($direction === 'desc' ? ' desc nulls last' : ' asc nulls first'));
    }

    /**
     * @param  Builder<Entity>  $query
     */
    public static function selectLevelKey(Builder $query): void
    {
        $query->withAggregate('levelCertificate as '.self::LEVEL_KEY, DB::raw(self::LEVEL_KEY_EXPRESSION));
    }
}
