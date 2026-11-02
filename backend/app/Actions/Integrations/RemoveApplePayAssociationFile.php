<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Exceptions\Integrations\IntegrationRejected;
use App\Services\Integrations\ApplePayAssociationFile;
use Illuminate\Support\Facades\DB;

final readonly class RemoveApplePayAssociationFile
{
    public function __construct(private ApplePayAssociationFile $file) {}

    public function handle(): void
    {
        if (! DB::transaction(fn (): bool => $this->file->remove())) {
            throw IntegrationRejected::associationFileMissing();
        }
    }
}
