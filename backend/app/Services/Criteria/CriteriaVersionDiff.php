<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Data\Criteria\CriteriaChange;
use App\Enums\Criteria\CriteriaChangeType;
use App\Enums\Criteria\CriteriaNodeType;
use App\Models\Criteria\CriteriaVersion;

final readonly class CriteriaVersionDiff
{
    private const array IDENTITY_FIELDS = ['code'];

    public function __construct(private CriteriaNodeSnapshot $snapshot) {}

    /**
     * @return list<CriteriaChange>
     */
    public function between(CriteriaVersion $from, CriteriaVersion $to): array
    {
        $before = $this->snapshotsOf($from);
        $after = $this->snapshotsOf($to);
        $changes = [];

        foreach ([CriteriaNodeType::Main, CriteriaNodeType::Sub, CriteriaNodeType::Point] as $type) {
            array_push($changes, ...$this->compare($type, $before[$type->value] ?? [], $after[$type->value] ?? []));
        }

        return $changes;
    }

    /**
     * @param  array<string, array{id: int, fields: array<string, mixed>}>  $before
     * @param  array<string, array{id: int, fields: array<string, mixed>}>  $after
     * @return list<CriteriaChange>
     */
    private function compare(CriteriaNodeType $type, array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $code => $node) {
            $previous = $before[$code] ?? null;
            $change = $previous === null
                ? new CriteriaChange($type, (string) $code, CriteriaChangeType::Added, null, $node['id'])
                : $this->modification($type, (string) $code, $previous, $node);

            if ($change instanceof CriteriaChange) {
                $changes[] = $change;
            }
        }

        foreach (array_diff_key($before, $after) as $code => $node) {
            $changes[] = new CriteriaChange($type, (string) $code, CriteriaChangeType::Removed, $node['id'], null);
        }

        return $changes;
    }

    /**
     * @param  array{id: int, fields: array<string, mixed>}  $previous
     * @param  array{id: int, fields: array<string, mixed>}  $current
     */
    private function modification(CriteriaNodeType $type, string $code, array $previous, array $current): ?CriteriaChange
    {
        $fields = [];

        foreach ($current['fields'] as $field => $value) {
            if (($previous['fields'][$field] ?? null) !== $value) {
                $fields[] = $field;
            }
        }

        if ($fields === []) {
            return null;
        }

        return new CriteriaChange(
            $type,
            $code,
            CriteriaChangeType::Modified,
            $previous['id'],
            $current['id'],
            $fields,
            array_intersect_key($previous['fields'], array_flip($fields)),
            array_intersect_key($current['fields'], array_flip($fields)),
        );
    }

    /**
     * @return array<string, array<string, array{id: int, fields: array<string, mixed>}>>
     */
    private function snapshotsOf(CriteriaVersion $version): array
    {
        $snapshots = [];

        foreach ($version->mainCriteria as $main) {
            $snapshots[CriteriaNodeType::Main->value][$main->code] = $this->entry($main->id, $this->snapshot->ofMain($main));

            foreach ($main->subCriteria as $sub) {
                $snapshots[CriteriaNodeType::Sub->value][$sub->code] = $this->entry($sub->id, $this->snapshot->ofSub($sub));

                foreach ($sub->guidingPoints as $point) {
                    $snapshots[CriteriaNodeType::Point->value][$point->code] = $this->entry($point->id, $this->snapshot->ofPoint($point));
                }
            }
        }

        return $snapshots;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{id: int, fields: array<string, mixed>}
     */
    private function entry(int $id, array $fields): array
    {
        return ['id' => $id, 'fields' => array_diff_key($fields, array_flip(self::IDENTITY_FIELDS))];
    }
}
