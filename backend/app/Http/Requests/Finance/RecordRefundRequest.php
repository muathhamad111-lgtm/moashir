<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\RefundInstruction;
use App\Services\Files\FileSettings;
use App\Support\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use LogicException;

final class RecordRefundRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = Date::now(config()->string('app.display_timezone'))->toDateString();

        return [
            'amount' => ['required', 'string', 'regex:/^\d{1,10}\.\d{2}$/'],
            'refunded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
            'transfer_reference' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[\pL\pN\s\/._-]+$/u'],
            'evidence' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.app(FileSettings::class)->maxUploadKilobytes()],
        ];
    }

    public function instruction(): RefundInstruction
    {
        $evidence = $this->file('evidence');

        if (! $evidence instanceof UploadedFile) {
            throw new LogicException('The evidence file is validated before the instruction is built.');
        }

        return new RefundInstruction(
            new Money($this->string('amount')->toString()),
            CarbonImmutable::createFromFormat('Y-m-d', $this->string('refunded_on')->toString()) ?: CarbonImmutable::now(),
            trim($this->string('reason')->toString()),
            trim($this->string('transfer_reference')->toString()),
            $evidence,
        );
    }
}
