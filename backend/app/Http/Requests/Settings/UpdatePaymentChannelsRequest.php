<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Settings\PaymentChannelsUpdate;
use App\Enums\Finance\PaymentChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePaymentChannelsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channels' => ['required', 'array', 'list', 'min:1', 'max:'.count(PaymentChannel::cases())],
            'channels.*' => ['required', 'string', 'distinct', Rule::enum(PaymentChannel::class)],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function toUpdate(): PaymentChannelsUpdate
    {
        $channels = [];

        foreach (PaymentChannel::cases() as $channel) {
            if (in_array($channel->value, $this->array('channels'), true)) {
                $channels[] = $channel;
            }
        }

        return new PaymentChannelsUpdate($channels, $this->filled('reason') ? $this->string('reason')->trim()->toString() : null);
    }
}
