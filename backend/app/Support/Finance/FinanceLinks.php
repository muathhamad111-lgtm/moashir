<?php

declare(strict_types=1);

namespace App\Support\Finance;

final class FinanceLinks
{
    public static function myPayment(string $paymentPublicId): string
    {
        return '/payments/'.$paymentPublicId;
    }

    public static function myPayments(): string
    {
        return '/payments';
    }

    public static function transaction(string $paymentPublicId): string
    {
        return '/finance/transactions/'.$paymentPublicId;
    }

    public static function bankTransfer(string $paymentPublicId): string
    {
        return '/finance/transfers/'.$paymentPublicId;
    }

    public static function bankTransfers(): string
    {
        return '/finance/transfers';
    }

    public static function paymentSettings(): string
    {
        return '/admin/settings/payment';
    }

    public static function export(string $exportPublicId): string
    {
        return '/finance/exports/'.$exportPublicId;
    }
}
