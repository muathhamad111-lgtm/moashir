<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Data\Audit\AuditLogExport;
use App\Support\CsvCell;
use App\Support\ScalarValue;

final readonly class AuditLogCsvWriter
{
    private const string BYTE_ORDER_MARK = "\u{FEFF}";

    private const array COLUMNS = [
        'id', 'created_at', 'event', 'actor_kind', 'actor_id', 'actor_name', 'subject_type', 'subject_id',
        'ip_address', 'reason', 'old_values', 'new_values', 'request_id',
    ];

    public function __construct(private AuditLogPresenter $presenter) {}

    /**
     * @param  resource  $stream
     */
    public function write(AuditLogExport $export, $stream): void
    {
        fwrite($stream, self::BYTE_ORDER_MARK);
        $this->writeRow($stream, self::COLUMNS);

        foreach ($export->rows as $audit) {
            $this->writeRow($stream, $this->row($this->presenter->present($audit, $export->viewer)));
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    private function row(array $entry): array
    {
        $actor = is_array($entry['actor']) ? $entry['actor'] : [];
        $subject = is_array($entry['subject']) ? $entry['subject'] : [];

        return [
            ScalarValue::string($entry['id']),
            ScalarValue::string($entry['created_at']),
            ScalarValue::string($entry['event']),
            ScalarValue::string($actor['kind'] ?? null),
            ScalarValue::string($actor['id'] ?? null),
            ScalarValue::string($actor['name'] ?? null),
            ScalarValue::string($subject['type'] ?? null),
            ScalarValue::string($subject['id'] ?? null),
            ScalarValue::string($entry['ip_address']),
            ScalarValue::string($entry['reason']),
            $this->json($entry['old_values']),
            $this->json($entry['new_values']),
            ScalarValue::string($entry['request_id']),
        ];
    }

    private function json(mixed $values): string
    {
        return $values === null ? '' : (string) json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $cells
     */
    private function writeRow($stream, array $cells): void
    {
        fputcsv($stream, array_map(CsvCell::neutralize(...), $cells), ',', '"', '');
    }
}
