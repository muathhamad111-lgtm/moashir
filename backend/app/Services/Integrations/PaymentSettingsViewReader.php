<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Integrations\PaymentSettingsView;
use App\Enums\Integrations\IntegrationKind;

final readonly class PaymentSettingsViewReader
{
    public function __construct(private IntegrationSnapshotReader $snapshots, private ApplePayAssociationFile $associationFile) {}

    public function read(): PaymentSettingsView
    {
        return new PaymentSettingsView($this->snapshots->read(IntegrationKind::Payment), $this->associationFile->current());
    }
}
