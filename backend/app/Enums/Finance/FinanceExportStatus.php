<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum FinanceExportStatus: string
{
    case Streamed = 'streamed';
    case Queued = 'queued';
    case Ready = 'ready';
    case Failed = 'failed';
}
