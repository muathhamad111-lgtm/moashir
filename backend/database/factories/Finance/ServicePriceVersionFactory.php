<?php

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Enums\Assessments\PathAvailability;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Finance\ServicePriceVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<ServicePriceVersion>
 */
final class ServicePriceVersionFactory extends Factory
{
    protected $model = ServicePriceVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purpose' => PaymentPurpose::Accredited,
            'price_before_vat' => '1000.00',
            'availability' => PathAvailability::Available,
            'effective_from' => Date::now()->subDay(),
        ];
    }

    public function of(PaymentPurpose $purpose, string $price): self
    {
        return $this->state(['purpose' => $purpose, 'price_before_vat' => $price]);
    }
}
