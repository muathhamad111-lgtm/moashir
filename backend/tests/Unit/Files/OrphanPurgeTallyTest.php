<?php

declare(strict_types=1);

use App\Enums\Files\StoredFilePurpose;
use App\Models\Files\StoredFile;
use App\Support\Files\OrphanPurgeTally;

function tallyFile(StoredFilePurpose $purpose, int $size): StoredFile
{
    return new StoredFile()->forceFill(['purpose' => $purpose, 'size_bytes' => $size]);
}

it('counts purged files, bytes and purposes without any file detail', function (): void {
    $tally = new OrphanPurgeTally(24);
    $tally->add(tallyFile(StoredFilePurpose::RefundEvidence, 2048));
    $tally->add(tallyFile(StoredFilePurpose::FinanceExport, 512));
    $tally->add(tallyFile(StoredFilePurpose::RefundEvidence, 1024));

    expect($tally->isEmpty())->toBeFalse()
        ->and($tally->toArray())->toBe([
            'purged_files' => 3,
            'purged_bytes' => 3584,
            'by_purpose' => ['finance_export' => 1, 'refund_evidence' => 2],
            'grace_hours' => 24,
        ]);
});

it('starts empty', function (): void {
    expect(new OrphanPurgeTally(48)->isEmpty())->toBeTrue()
        ->and(new OrphanPurgeTally(48)->toArray()['purged_files'])->toBe(0);
});

it('keeps the Apple Pay association file out of the owner-bound purposes', function (): void {
    expect(StoredFilePurpose::ownerBoundValues())->toBe(['refund_evidence', 'receipt_pdf', 'finance_export', 'assessment_evidence', 'evidence_bundle', 'cms_image', 'kpi_image', 'branding_logo', 'certificate_pdf', 'entity_logo'])
        ->and(StoredFilePurpose::ApplePayDomainAssociation->isOwnerBound())->toBeFalse();
});
