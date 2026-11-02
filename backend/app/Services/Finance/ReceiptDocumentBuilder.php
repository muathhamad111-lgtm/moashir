<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Documents\PdfDocument;
use App\Models\Finance\PaymentReceipt;
use App\Support\HijriDate;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Config\Repository;

final readonly class ReceiptDocumentBuilder
{
    public const string TEMPLATE = 'payment_receipt';

    public const string TEST_WATERMARK = 'TEST';

    public const string VOID_WATERMARK = 'VOID';

    public function __construct(private Repository $config) {}

    public function for(PaymentReceipt $receipt): PdfDocument
    {
        $timezone = $this->config->string('app.display_timezone');
        $issuedAt = $receipt->issued_at->copy()->setTimezone($timezone);

        return new PdfDocument(self::TEMPLATE, [
            ...$receipt->snapshot,
            'number' => $receipt->number,
            'is_test' => $receipt->is_test,
            'status' => $receipt->status->value,
            'issued_on' => $issuedAt->toDateString(),
            'issued_on_hijri' => ['ar' => HijriDate::format($issuedAt, 'ar', $timezone), 'en' => HijriDate::format($issuedAt, 'en', $timezone)],
            'purpose_label' => LocalizedText::fromTranslation('finance.purposes.'.ScalarValue::string($receipt->snapshot['purpose'] ?? null))->toArray(),
            'labels' => ['ar' => trans('finance.receipt', [], 'ar'), 'en' => trans('finance.receipt', [], 'en')],
        ], $this->watermarkOf($receipt));
    }

    public function fileNameOf(PaymentReceipt $receipt): string
    {
        return ($receipt->number ?? 'TEST-'.ScalarValue::string($receipt->snapshot['payment_reference'] ?? null)).'.pdf';
    }

    private function watermarkOf(PaymentReceipt $receipt): ?string
    {
        return match (true) {
            $receipt->is_test => self::TEST_WATERMARK,
            $receipt->isVoided() => self::VOID_WATERMARK,
            default => null,
        };
    }
}
