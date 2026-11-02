<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCvEntry;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Files\StoredFile;
use Illuminate\Support\Facades\DB;

final class AdvancedDraftEraser
{
    private const string DELETION_FLAG = 'moashir.draft_deletion';

    public function erase(Assessment $draft): void
    {
        $fileIds = [
            ...EvidenceFile::query()->where('assessment_id', $draft->id)->pluck('stored_file_id')->all(),
            ...EvidenceBundle::query()->where('assessment_id', $draft->id)->whereNotNull('stored_file_id')->pluck('stored_file_id')->all(),
        ];

        if ($fileIds !== []) {
            StoredFile::withTrashed()->whereKey($fileIds)->update(['attachable_type' => null, 'attachable_id' => null]);
        }

        EvidenceFile::query()->where('assessment_id', $draft->id)->delete();
        EvidenceBundle::query()->where('assessment_id', $draft->id)->delete();
        AssessmentCvEntry::query()->where('assessment_id', $draft->id)->delete();

        DB::select('SELECT set_config(?, ?, true)', [self::DELETION_FLAG, (string) $draft->id]);
        Assessment::query()->whereKey($draft->id)->delete();
        DB::select('SELECT set_config(?, ?, true)', [self::DELETION_FLAG, '']);
    }
}
