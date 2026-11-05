<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Finance\OpenedRequestLookup;
use App\Data\Finance\OpenedRequest;
use App\Models\Assessments\Assessment;

final class OpenedRequestsByPayment implements OpenedRequestLookup
{
    public function forPayments(array $paymentIds): array
    {
        if ($paymentIds === []) {
            return [];
        }

        $requests = [];

        foreach (Assessment::query()->whereIn('payment_id', $paymentIds)->get(['id', 'request_number', 'path', 'payment_id']) as $assessment) {
            $requests[(int) $assessment->payment_id] = new OpenedRequest($assessment->id, $assessment->request_number, $assessment->path);
        }

        return $requests;
    }
}
