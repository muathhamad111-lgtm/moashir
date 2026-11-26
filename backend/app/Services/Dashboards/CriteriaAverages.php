<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CriteriaAverages
{
    /**
     * @param  Builder<Assessment>  $assessments
     * @return list<array{code: string, name: array<string, string>|null, average_percent: string, results: int}>
     */
    public function lowestMains(Builder $assessments, int $limit): array
    {
        $rows = DB::table('assessment_result_snapshots as snapshot')
            ->crossJoin(DB::raw("jsonb_array_elements(snapshot.breakdown -> 'mains') as main"))
            ->whereIn('snapshot.id', (clone $assessments)->reorder()->whereNotNull('current_result_snapshot_id')->select('current_result_snapshot_id'))
            ->selectRaw("main ->> 'code' as code, round(avg((main ->> 'percent')::numeric), 2) as average_percent, count(*) as results")
            ->groupByRaw("main ->> 'code'")
            ->orderBy('average_percent')
            ->orderBy('code')
            ->limit($limit)
            ->get();

        $codes = array_values($rows->map(static fn (object $row): string => ScalarValue::string(get_object_vars($row)['code'] ?? null))->all());
        $names = $this->publishedNames($codes);

        return array_values($rows->map(static function (object $row) use ($names): array {
            $values = get_object_vars($row);
            $code = ScalarValue::string($values['code'] ?? null);

            return [
                'code' => $code,
                'name' => $names[$code] ?? null,
                'average_percent' => ScalarValue::string($values['average_percent'] ?? null, '0.00'),
                'results' => ScalarValue::int($values['results'] ?? null),
            ];
        })->all());
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, array<string, string>>
     */
    private function publishedNames(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $names = [];

        foreach (MainCriterion::query()->whereIn('criteria_version_id', CriteriaVersion::query()->published()->select('id'))->whereIn('code', $codes)->get(['code', 'name']) as $main) {
            $names[$main->code] = $main->name->toArray();
        }

        return $names;
    }
}
