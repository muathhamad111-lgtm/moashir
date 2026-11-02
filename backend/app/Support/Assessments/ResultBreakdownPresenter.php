<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Data\Assessments\CriteriaNodeNames;
use App\Support\ScalarValue;
use App\Support\ValueObjects\EarnedScore;
use App\Support\ValueObjects\Percent;

final readonly class ResultBreakdownPresenter
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $mains;

    /**
     * @param  array<string, mixed>  $breakdown
     */
    public function __construct(array $breakdown, private CriteriaNodeNames $names)
    {
        $this->mains = $this->rows($breakdown['mains'] ?? []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mains(): array
    {
        return array_map(fn (array $main): array => [
            'criterion_id' => ScalarValue::int($main['id'] ?? null),
            'code' => ScalarValue::string($main['code'] ?? null),
            'name' => $this->names->main(ScalarValue::int($main['id'] ?? null))?->toArray(),
            ...$this->figures($main),
        ], $this->mains);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function subs(): array
    {
        $rows = [];

        foreach ($this->mains as $main) {
            foreach ($this->rows($main['subs'] ?? []) as $sub) {
                $rows[] = [
                    'sub_id' => ScalarValue::int($sub['id'] ?? null),
                    'main_criterion_id' => ScalarValue::int($main['id'] ?? null),
                    'code' => ScalarValue::string($sub['code'] ?? null),
                    'name' => $this->names->sub(ScalarValue::int($sub['id'] ?? null))?->toArray(),
                    'evaluation_type' => ScalarValue::string($sub['evaluation_type'] ?? null),
                    'raw_value' => $this->nullableString($sub['raw_value'] ?? null),
                    ...$this->figures($sub),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function points(): array
    {
        $rows = [];

        foreach ($this->mains as $main) {
            foreach ($this->rows($main['subs'] ?? []) as $sub) {
                foreach ($this->rows($sub['points'] ?? []) as $point) {
                    $rows[] = [
                        'point_id' => ScalarValue::int($point['id'] ?? null),
                        'sub_criterion_id' => ScalarValue::int($sub['id'] ?? null),
                        'code' => ScalarValue::string($point['code'] ?? null),
                        'description' => $this->names->point(ScalarValue::int($point['id'] ?? null))?->toArray(),
                        'raw_value' => $this->nullableString($point['raw_value'] ?? null),
                        ...$this->figures($point),
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{weight: string, earned: string, percent: string}
     */
    private function figures(array $node): array
    {
        return [
            'weight' => ScalarValue::string($node['weight'] ?? null),
            'earned' => EarnedScore::of(ScalarValue::string($node['earned'] ?? null, '0'))->toDisplay(),
            'percent' => Percent::of(ScalarValue::string($node['percent'] ?? null, '0'))->toDisplay(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $typed = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $typed[] = array_filter($row, is_string(...), ARRAY_FILTER_USE_KEY);
            }
        }

        return $typed;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
