<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;

final readonly class PaymentPeriodFilter
{
    public function __construct(private Repository $config) {}

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function apply(Builder $query, ?CarbonImmutable $from, ?CarbonImmutable $to): Builder
    {
        return $query
            ->when($from instanceof CarbonImmutable, fn (Builder $inner): Builder => $inner->where('created_at', '>=', $this->startOf($from)))
            ->when($to instanceof CarbonImmutable, fn (Builder $inner): Builder => $inner->where('created_at', '<', $this->startOf($to)->addDay()));
    }

    private function startOf(?CarbonImmutable $date): CarbonImmutable
    {
        return CarbonImmutable::parse(($date ?? CarbonImmutable::now())->toDateString(), $this->config->string('app.display_timezone'))->utc();
    }
}
