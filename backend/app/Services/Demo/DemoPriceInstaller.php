<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Prices\PriceChange;
use App\Enums\Assessments\PathAvailability;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Finance\ServicePriceVersion;
use App\Models\Identity\User;
use App\Services\Prices\PriceVersions;
use App\Services\Prices\PriceVersionWriter;
use App\Support\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

final readonly class DemoPriceInstaller
{
    private const string ACCREDITED_PRICE_BEFORE_VAT = '2500.00';

    public function __construct(private PriceVersions $versions, private PriceVersionWriter $writer) {}

    public function install(User $systemAdmin): void
    {
        if ($this->versions->inForce(PaymentPurpose::Accredited) instanceof ServicePriceVersion) {
            return;
        }

        $change = new PriceChange(new Money(self::ACCREDITED_PRICE_BEFORE_VAT), PathAvailability::Available, null, null, null);

        DB::transaction(fn () => $this->writer->write($systemAdmin, PaymentPurpose::Accredited, $change));
    }
}
