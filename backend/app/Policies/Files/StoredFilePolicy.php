<?php

declare(strict_types=1);

namespace App\Policies\Files;

use App\Enums\Entities\ProfileChangeStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Certificates\Certificate;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Files\StoredFile;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\FinanceExport;
use App\Models\Finance\PaymentReceipt;
use App\Models\Finance\Refund;
use App\Models\Identity\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class StoredFilePolicy
{
    public function download(User $actor, StoredFile $file): Response
    {
        $owner = $file->attachable;

        $ability = match (true) {
            $file->purpose === StoredFilePurpose::RefundEvidence && $owner instanceof Refund => 'downloadEvidence',
            $file->purpose === StoredFilePurpose::TransferProof && $owner instanceof BankTransfer => 'downloadProof',
            $file->purpose === StoredFilePurpose::ReceiptPdf && $owner instanceof PaymentReceipt => 'download',
            $file->purpose === StoredFilePurpose::FinanceExport && $owner instanceof FinanceExport => 'download',
            $file->purpose === StoredFilePurpose::AssessmentEvidence && $owner instanceof EvidenceFile => 'download',
            $file->purpose === StoredFilePurpose::EvidenceBundle && $owner instanceof EvidenceBundle => 'download',
            $file->purpose === StoredFilePurpose::CertificatePdf && $owner instanceof Certificate => 'download',
            $file->purpose === StoredFilePurpose::EntityLogo && $owner instanceof Entity && $owner->logo_file_id === $file->id => 'viewLogo',
            $file->purpose === StoredFilePurpose::VatCertificate && $owner instanceof Entity && $owner->vat_certificate_file_id === $file->id => 'viewVatCertificate',
            $owner instanceof Entity && $this->isPendingProfileFile($owner, $file) => 'viewPendingProfileFile',
            default => null,
        };

        return $ability === null || $owner === null ? Response::denyAsNotFound() : Gate::forUser($actor)->inspect($ability, $owner);
    }

    private function isPendingProfileFile(Entity $entity, StoredFile $file): bool
    {
        return EntityProfileChangeRequest::query()
            ->where('entity_id', $entity->id)
            ->where('status', ProfileChangeStatus::Pending)
            ->where(static fn (Builder $pending): Builder => $pending->where('pending_logo_file_id', $file->id)->orWhere('pending_vat_certificate_file_id', $file->id))
            ->exists();
    }
}
