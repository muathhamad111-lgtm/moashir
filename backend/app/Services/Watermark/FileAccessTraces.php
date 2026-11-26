<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use App\Enums\Files\AccessIntent;
use App\Models\Files\FileAccessTrace;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Support\ValueObjects\TraceCode;
use Illuminate\Http\Request;

final readonly class FileAccessTraces
{
    public function __construct(private Request $request) {}

    public function record(User $viewer, StoredFile $file, AccessIntent $intent, TraceCode $code): FileAccessTrace
    {
        return FileAccessTrace::query()->create([
            'trace_code' => $code->value,
            'user_id' => $viewer->id,
            'stored_file_id' => $file->id,
            'stored_file_public_id' => $file->public_id,
            'purpose' => $file->purpose,
            'intent' => $intent,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
