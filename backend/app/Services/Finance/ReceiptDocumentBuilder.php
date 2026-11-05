<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Documents\PdfDocument;
use App\Enums\Documents\DocumentTemplate;
use App\Enums\Documents\DocumentWatermark;
use App\Models\Finance\PaymentReceipt;
use App\Support\HijriDate;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;

final readonly class ReceiptDocumentBuilder
{
    public const string TEMPLATE = DocumentTemplate::PaymentReceipt->value;

    public const string TEST_WATERMARK = DocumentWatermark::Test->value;

    public const string VOID_WATERMARK = DocumentWatermark::Void->value;

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
            'currency_label' => LocalizedText::fromTranslation('finance.currencies.'.ScalarValue::string($receipt->snapshot['currency'] ?? null))->toArray(),
            'method_label' => $this->methodLabelOf($receipt),
            'paid_on' => $this->paidOnOf($receipt, $timezone),
            'labels' => ['ar' => trans('finance.receipt', [], 'ar'), 'en' => trans('finance.receipt', [], 'en')],
        ], $this->watermarkOf($receipt)?->value);
    }

    public function fileNameOf(PaymentReceipt $receipt): string
    {
        return ($receipt->number ?? 'TEST-'.ScalarValue::string($receipt->snapshot['payment_reference'] ?? null)).'.pdf';
    }

    private function watermarkOf(PaymentReceipt $receipt): ?DocumentWatermark
    {
        return match (true) {
            $receipt->is_test => DocumentWatermark::Test,
            $receipt->isVoided() => DocumentWatermark::Void,
            default => null,
        };
    }

    /**
     * @return array{ar: string, en: string}|null
     */
    private function methodLabelOf(PaymentReceipt $receipt): ?array
    {
        $method = $receipt->snapshot['method'] ?? null;

        return is_string($method) && $method !== '' ? LocalizedText::fromTranslation('finance.methods.'.$method)->toArray() : null;
    }

    private function paidOnOf(PaymentReceipt $receipt, string $timezone): ?string
    {
        $paidAt = $receipt->snapshot['paid_at'] ?? null;

        return is_string($paidAt) && $paidAt !== '' ? Date::parse($paidAt)->setTimezone($timezone)->toDateString() : null;
    }
}
