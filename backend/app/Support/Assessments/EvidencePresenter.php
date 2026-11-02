<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Data\Assessments\BoundSubCriteria;
use App\Models\Assessments\EvidenceFile;

final class EvidencePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function item(EvidenceFile $evidence, BoundSubCriteria $subs): array
    {
        $file = $evidence->storedFile;

        return [
            'id' => $evidence->public_id,
            'sub_criterion_id' => $subs->idOf($evidence->sub_code),
            'code' => $evidence->sub_code,
            'attachment_key' => $evidence->attachment_key,
            'file_name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
            'scan_status' => $file->malware_scan_status->value,
            'uploaded_at' => $evidence->created_at->toIso8601String(),
        ];
    }

    /**
     * @param  list<EvidenceFile>  $evidence
     * @return list<array<string, mixed>>
     */
    public static function list(array $evidence, BoundSubCriteria $subs): array
    {
        return array_map(static fn (EvidenceFile $file): array => self::item($file, $subs), $evidence);
    }
}
