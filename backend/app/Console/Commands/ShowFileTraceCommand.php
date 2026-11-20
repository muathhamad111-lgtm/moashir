<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Files\FileAccessTrace;
use App\Support\ValueObjects\TraceCode;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:files:trace')]
final class ShowFileTraceCommand extends Command
{
    protected $signature = 'moashir:files:trace {code : The trace code printed on a stamped file, e.g. MSR-7K3F-9Q2D-4XHB}';

    protected $description = 'Show who received a stamped file, when, from which address and for what.';

    public function handle(): int
    {
        $code = TraceCode::tryFrom((string) $this->argument('code'));
        $trace = $code instanceof TraceCode ? FileAccessTrace::query()->with('user')->where('trace_code', $code->value)->first() : null;

        if (! $trace instanceof FileAccessTrace) {
            $this->components->error('No stamped file carries this trace code.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Trace code', $trace->trace_code);
        $this->components->twoColumnDetail('Viewer', sprintf('%s (user #%d)', $trace->user->name ?? '-', $trace->user_id));
        $this->components->twoColumnDetail('File', sprintf('%s (%s)', $trace->stored_file_public_id, $trace->purpose->value));
        $this->components->twoColumnDetail('Intent', $trace->intent->value);
        $this->components->twoColumnDetail('Time (UTC)', $trace->created_at->toIso8601String());
        $this->components->twoColumnDetail('IP address', $trace->ip_address ?? '-');

        return self::SUCCESS;
    }
}
