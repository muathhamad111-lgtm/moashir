<?php

declare(strict_types=1);

namespace App\Policies\Files;

use App\Enums\Files\StoredFilePurpose;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Files\StoredFile;
use App\Models\Finance\FinanceExport;
use App\Models\Finance\PaymentReceipt;
use App\Models\Finance\Refund;
use App\Models\Identity\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

final class StoredFilePolicy
{
    public function download(User $actor, StoredFile $file): Response
    {
        $owner = $file->attachable;

        $ability = match (true) {
            $file->purpose === StoredFilePurpose::RefundEvidence && $owner instanceof Refund => 'downloadEvidence',
            $file->purpose === StoredFilePurpose::ReceiptPdf && $owner instanceof PaymentReceipt => 'download',
            $file->purpose === StoredFilePurpose::FinanceExport && $owner instanceof FinanceExport => 'download',
            $file->purpose === StoredFilePurpose::AssessmentEvidence && $owner instanceof EvidenceFile => 'download',
            $file->purpose === StoredFilePurpose::EvidenceBundle && $owner instanceof EvidenceBundle => 'download',
            default => null,
        };

        return $ability === null || $owner === null ? Response::denyAsNotFound() : Gate::forUser($actor)->inspect($ability, $owner);
    }
}
