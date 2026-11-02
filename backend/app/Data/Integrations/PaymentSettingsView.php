<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Models\Files\StoredFile;

final readonly class PaymentSettingsView
{
    public function __construct(public IntegrationSnapshot $snapshot, public ?StoredFile $applePayAssociationFile) {}
}
