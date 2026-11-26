<?php

declare(strict_types=1);

use App\Data\Assessments\AttachmentSlot;
use App\Enums\Files\EvidenceFileType;

it('takes the file types it names within the path types', function (): void {
    $slot = new AttachmentSlot('plan', true, [EvidenceFileType::Pdf, EvidenceFileType::Png]);

    expect($slot->typesWithin([EvidenceFileType::Pdf, EvidenceFileType::Docx]))->toBe([EvidenceFileType::Pdf]);
});

it('takes every path type when it names none or none of them is allowed any more', function (): void {
    $path = [EvidenceFileType::Pdf, EvidenceFileType::Docx];

    expect(new AttachmentSlot('plan', true)->typesWithin($path))->toBe($path)
        ->and(new AttachmentSlot('plan', true, [EvidenceFileType::Png])->typesWithin($path))->toBe($path);
});

it('takes at most its own file count and never more than the path allows', function (): void {
    expect(new AttachmentSlot('plan', true, [], 3)->maxFilesWithin(10))->toBe(3)
        ->and(new AttachmentSlot('plan', true, [], 30)->maxFilesWithin(10))->toBe(10)
        ->and(new AttachmentSlot('plan', true)->maxFilesWithin(10))->toBe(10);
});
