<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Certificates\Certificate;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\PaymentReceipt;
use App\Models\Finance\Refund;
use App\Services\Files\StoredFileDisk;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final readonly class DemoFiles
{
    public function __construct(private StoredFileDisk $disk) {}

    /**
     * @param  list<int>  $assessmentIds
     * @return list<int>
     */
    public function ofAssessments(array $assessmentIds): array
    {
        $certificates = Certificate::query()->whereIn('assessment_id', $assessmentIds);

        return $this->idsOf([
            EvidenceFile::query()->whereIn('assessment_id', $assessmentIds)->pluck('stored_file_id')->all(),
            EvidenceBundle::query()->whereIn('assessment_id', $assessmentIds)->pluck('stored_file_id')->all(),
            (clone $certificates)->pluck('pdf_file_id')->all(),
            (clone $certificates)->pluck('entity_logo_file_id')->all(),
        ]);
    }

    /**
     * @param  list<int>  $paymentIds
     * @return list<int>
     */
    public function ofPayments(array $paymentIds): array
    {
        return $this->idsOf([
            PaymentReceipt::query()->whereIn('payment_id', $paymentIds)->pluck('pdf_file_id')->all(),
            Refund::query()->whereIn('payment_id', $paymentIds)->pluck('evidence_file_id')->all(),
            BankTransfer::query()->whereIn('payment_id', $paymentIds)->pluck('proof_file_id')->all(),
        ]);
    }

    /**
     * @param  list<int>  $ownerIds
     * @return list<int>
     */
    public function ofEntity(Entity $entity, array $ownerIds): array
    {
        return $this->idsOf([
            [$entity->logo_file_id],
            StoredFile::withTrashed()->where(fn (Builder $files): Builder => $files
                ->whereMorphedTo('attachable', $entity)
                ->orWhereIn('uploaded_by_user_id', $ownerIds))
                ->pluck('id')
                ->all(),
        ]);
    }

    /**
     * @param  list<int>  $fileIds
     * @return list<StoredFile>
     */
    public function removeRecords(array $fileIds): array
    {
        $files = array_values(StoredFile::withTrashed()->whereKey($fileIds)->get()->all());
        StoredFile::withTrashed()->whereKey($fileIds)->forceDelete();

        return $files;
    }

    /**
     * @param  list<StoredFile>  $files
     */
    public function removeContents(array $files): void
    {
        foreach ($files as $file) {
            $this->disk->delete($file);
        }
    }

    /**
     * @param  list<array<array-key, mixed>>  $groups
     * @return list<int>
     */
    private function idsOf(array $groups): array
    {
        $ids = array_filter(array_merge(...$groups), static fn (mixed $id): bool => $id !== null);

        return array_values(array_unique(array_map(static fn (mixed $id): int => ScalarValue::int($id), $ids)));
    }
}
