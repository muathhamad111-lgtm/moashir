<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Identity\UserExport;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;
use App\Support\CsvCell;
use App\Support\ScalarValue;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Carbon;

final readonly class UserCsvWriter
{
    private const string BYTE_ORDER_MARK = "\u{FEFF}";

    private const array COLUMNS = ['name', 'email', 'roles', 'status', 'last_login_at', 'created_at'];

    public function __construct(private Repository $config) {}

    /**
     * @param  resource  $stream
     */
    public function write(UserExport $export, $stream): void
    {
        fwrite($stream, self::BYTE_ORDER_MARK);
        $this->writeRow($stream, array_map(
            fn (string $column): string => $this->label('users.export.columns.'.$column, $export->locale),
            self::COLUMNS,
        ));

        foreach ($export->users as $user) {
            $this->writeRow($stream, $this->row($user, $export->locale));
        }
    }

    /**
     * @return list<string>
     */
    public function row(User $user, string $locale): array
    {
        return [
            $user->name,
            $user->email,
            implode($this->label('users.export.role_separator', $locale), array_map(
                fn (RoleKey $role): string => $this->label('roles.'.$role->value.'.label', $locale),
                $user->roleKeys(),
            )),
            $this->label('users.statuses.'.$user->effectiveStatus()->value, $locale),
            $this->moment($user->last_login_at),
            $this->moment($user->created_at),
        ];
    }

    private function moment(?Carbon $moment): string
    {
        return $moment?->copy()->setTimezone($this->config->string('app.display_timezone'))->toIso8601String() ?? '';
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $cells
     */
    private function writeRow($stream, array $cells): void
    {
        fputcsv($stream, array_map(CsvCell::neutralize(...), $cells), ',', '"', '');
    }

    private function label(string $key, string $locale): string
    {
        return ScalarValue::string(__($key, [], $locale));
    }
}
