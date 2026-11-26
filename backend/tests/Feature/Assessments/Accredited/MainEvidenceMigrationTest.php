<?php

declare(strict_types=1);

use App\Models\Assessments\EvidenceFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function mainEvidenceMigration(): object
{
    return require database_path('migrations/2026_11_20_120000_add_main_code_to_evidence_files.php');
}

function storedEvidenceRow(int $assessmentId, array $owner): void
{
    DB::table('evidence_files')->insert([
        'assessment_id' => $assessmentId,
        'stored_file_id' => storedTestFile()->id,
        'status' => 'uploaded',
        ...$owner,
    ]);
}

it('holds a file under exactly one main criterion or sub-criterion', function (): void {
    publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();

    storedEvidenceRow($assessment->id, ['main_code' => 'M1', 'attachment_key' => 'plan']);
    storedEvidenceRow($assessment->id, ['sub_code' => 'M1.1']);

    expect(fn () => storedEvidenceRow($assessment->id, ['main_code' => 'M1', 'sub_code' => 'M1.1']))->toThrow(QueryException::class)
        ->and(fn () => storedEvidenceRow($assessment->id, []))->toThrow(QueryException::class);
});

it('rolls back to sub-criterion evidence only and comes back with main criterion evidence', function (): void {
    publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    storedEvidenceRow($assessment->id, ['main_code' => 'M1', 'attachment_key' => 'plan']);
    storedEvidenceRow($assessment->id, ['sub_code' => 'M1.1']);
    $migration = mainEvidenceMigration();

    $migration->down();

    expect(DB::table('evidence_files')->pluck('sub_code')->all())->toBe(['M1.1']);

    $migration->up();
    storedEvidenceRow($assessment->id, ['main_code' => 'M2', 'attachment_key' => 'report']);

    expect(EvidenceFile::query()->whereNotNull('main_code')->value('main_code'))->toBe('M2');
});
