<?php

declare(strict_types=1);

use App\Models\Entities\Entity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function governanceGradeAnswerMigration(): object
{
    return require database_path('migrations/2026_11_26_110000_add_has_governance_grade_to_entities_table.php');
}

/**
 * @return list<string>
 */
function governanceGradeAnswerChecks(): array
{
    return DB::table('pg_constraint')
        ->whereIn('conname', ['entities_declined_governance_grade_check', 'entities_answered_governance_grade_check'])
        ->orderBy('conname')
        ->pluck('conname')
        ->all();
}

it('refuses a "no" answer that carries a grade and a "yes" answer without one', function (): void {
    $graded = Entity::factory()->create();
    $legacy = Entity::factory()->create(['governance_grade' => null]);

    expect(fn () => DB::transaction(fn () => DB::table('entities')->where('id', $graded->id)->update(['has_governance_grade' => false])))
        ->toThrow(QueryException::class, 'entities_declined_governance_grade_check')
        ->and(fn () => DB::transaction(fn () => DB::table('entities')->where('id', $legacy->id)->update(['has_governance_grade' => true])))
        ->toThrow(QueryException::class, 'entities_answered_governance_grade_check')
        ->and(fn () => DB::transaction(fn () => DB::table('entities')->where('id', $graded->id)->update(['governance_grade' => null])))
        ->toThrow(QueryException::class, 'entities_answered_governance_grade_check');

    DB::table('entities')->where('id', $graded->id)->update(['has_governance_grade' => false, 'governance_grade' => null]);

    expect(DB::table('entities')->where('id', $graded->id)->value('has_governance_grade'))->toBeFalse();
});

it('drops the answer and its checks on rollback and backfills a stored grade as "yes" when it runs again', function (): void {
    $migration = governanceGradeAnswerMigration();
    $graded = Entity::factory()->create();
    $declined = Entity::factory()->withoutGovernanceGrade()->create();
    $legacy = Entity::factory()->create(['governance_grade' => null]);

    $migration->down();

    expect(Schema::hasColumn('entities', 'has_governance_grade'))->toBeFalse()
        ->and(governanceGradeAnswerChecks())->toBe([]);

    $migration->up();

    expect(DB::table('entities')->orderBy('id')->pluck('has_governance_grade', 'id')->all())->toBe([
        $graded->id => true,
        $declined->id => null,
        $legacy->id => null,
    ])
        ->and(DB::table('entities')->where('id', $graded->id)->value('governance_grade'))->toBe('82.50')
        ->and(governanceGradeAnswerChecks())->toBe(['entities_answered_governance_grade_check', 'entities_declined_governance_grade_check']);
});
