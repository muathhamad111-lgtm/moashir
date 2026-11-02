<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Integrations\ApplePayAssociationFile;
use Illuminate\Http\UploadedFile;

final readonly class UploadApplePayAssociationFile
{
    public function __construct(private ApplePayAssociationFile $file) {}

    public function handle(User $actor, UploadedFile $upload): StoredFile
    {
        return $this->file->replace($upload, $actor);
    }
}
