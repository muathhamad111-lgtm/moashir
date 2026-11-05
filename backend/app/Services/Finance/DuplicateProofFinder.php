<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Files\StoredFilePurpose;
use App\Models\Files\StoredFile;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\Payment;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final class DuplicateProofFinder
{
    /**
     * @param  list<string>  $hashes
     * @return list<string>
     */
    public function duplicatedAmong(array $hashes): array
    {
        if ($hashes === []) {
            return [];
        }

        return array_values(array_map(ScalarValue::string(...), StoredFile::query()
            ->where('purpose', StoredFilePurpose::TransferProof)
            ->whereIn('sha256', $hashes)
            ->groupBy('sha256')
            ->havingRaw('count(*) > 1')
            ->pluck('sha256')
            ->all()));
    }

    /**
     * @return list<Payment>
     */
    public function otherPaymentsWithProofOf(BankTransfer $transfer): array
    {
        $sha256 = $transfer->proofFile->sha256;

        return array_values(Payment::query()
            ->whereHas('bankTransfer', static fn (Builder $other): Builder => $other
                ->whereKeyNot($transfer->id)
                ->whereHas('proofFile', static fn (Builder $file): Builder => $file->where('sha256', $sha256)))
            ->orderBy('created_at')
            ->get(['id', 'public_id', 'reference', 'status', 'created_at'])
            ->all());
    }
}
