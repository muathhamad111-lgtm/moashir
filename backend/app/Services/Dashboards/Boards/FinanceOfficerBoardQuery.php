<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Boards;

use App\Contracts\Dashboards\DashboardBoardQuery;
use App\Data\Dashboards\BoardContent;
use App\Data\Dashboards\DashboardCard;
use App\Data\Dashboards\DashboardWidget;
use App\Enums\Dashboards\CardFormat;
use App\Enums\Dashboards\DashboardBoard;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReceiptStatus;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Identity\User;
use App\Services\Dashboards\PaymentTotals;
use App\Support\Dashboards\DashboardLinks as Links;
use App\Support\ScalarValue;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Date;

final readonly class FinanceOfficerBoardQuery implements DashboardBoardQuery
{
    private const int DAILY_WINDOW_DAYS = 30;

    public function __construct(private PaymentTotals $payments, private Config $config) {}

    public function board(): DashboardBoard
    {
        return DashboardBoard::FinanceOfficer;
    }

    public function build(User $user): BoardContent
    {
        $totals = $this->payments->live();

        return new BoardContent([
            new DashboardCard('revenue', $totals['collected'], CardFormat::Money, Links::FINANCE),
            new DashboardCard('vat_collected', $totals['vat_collected'], CardFormat::Money, Links::FINANCE),
            new DashboardCard('payments_paid', $totals['paid_count'], link: Links::FINANCE_TRANSACTIONS),
            new DashboardCard('payments_open', $totals['open_count'], link: Links::FINANCE_TRANSACTIONS),
            new DashboardCard('refunds_total', $totals['refunded'], CardFormat::Money, Links::FINANCE_REFUNDS),
            new DashboardCard('refunds_count', $totals['refunded_count'], link: Links::FINANCE_REFUNDS),
            new DashboardCard('receipts_issued', PaymentReceipt::query()->where('status', ReceiptStatus::Issued)->where('is_test', false)->count(), link: Links::FINANCE_TRANSACTIONS),
        ], [
            new DashboardWidget('daily_payments', $this->daily()),
            new DashboardWidget('by_service', $this->byService()),
        ]);
    }

    /**
     * @return list<array{date: string, count: int, total: string}>
     */
    private function daily(): array
    {
        $timezone = $this->config->string('app.display_timezone');
        $since = Date::now($timezone)->startOfDay()->subDays(self::DAILY_WINDOW_DAYS - 1);

        return array_values(Payment::query()->live()->toBase()
            ->where('status', PaymentStatus::Paid->value)
            ->where('paid_at', '>=', $since->utc())
            ->selectRaw('cast((paid_at at time zone ?) as date) as day, count(*) as payments, cast(sum(total) as numeric(14, 2)) as total', [$timezone])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(static function (object $row): array {
                $values = get_object_vars($row);

                return [
                    'date' => ScalarValue::string($values['day'] ?? null),
                    'count' => ScalarValue::int($values['payments'] ?? null),
                    'total' => ScalarValue::string($values['total'] ?? null, '0.00'),
                ];
            })
            ->all());
    }

    /**
     * @return list<array{purpose: string, count: int, total: string}>
     */
    private function byService(): array
    {
        return array_values(Payment::query()->live()->toBase()
            ->where('status', PaymentStatus::Paid->value)
            ->selectRaw('purpose, count(*) as payments, cast(sum(total) as numeric(14, 2)) as total')
            ->groupBy('purpose')
            ->orderBy('purpose')
            ->get()
            ->map(static function (object $row): array {
                $values = get_object_vars($row);

                return [
                    'purpose' => ScalarValue::string($values['purpose'] ?? null),
                    'count' => ScalarValue::int($values['payments'] ?? null),
                    'total' => ScalarValue::string($values['total'] ?? null, '0.00'),
                ];
            })
            ->all());
    }
}
