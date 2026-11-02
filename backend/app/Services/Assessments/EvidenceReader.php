<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;
use Illuminate\Database\Eloquent\Builder;

final class EvidenceReader
{
    /**
     * @return list<EvidenceFile>
     */
    public function forOwner(Assessment $assessment, ?string $subCode = null): array
    {
        return array_values($this->query($assessment, $subCode)->get()->all());
    }

    /**
     * @return list<EvidenceFile>
     */
    public function cleanOnly(Assessment $assessment, ?string $subCode = null): array
    {
        return array_values($this->query($assessment, $subCode)->scannedClean()->get()->all());
    }

    /**
     * @return Builder<EvidenceFile>
     */
    private function query(Assessment $assessment, ?string $subCode): Builder
    {
        return EvidenceFile::query()
            ->with('storedFile')
            ->where('assessment_id', $assessment->id)
            ->present()
            ->when($subCode !== null, static fn (Builder $query): Builder => $query->where('sub_code', $subCode))
            ->orderBy('id');
    }
}
