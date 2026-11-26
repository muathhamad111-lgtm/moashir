<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Data\Nda\NdaRegisterExport;
use App\Enums\Nda\NdaUserStatus;
use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\User;
use App\Support\CsvCell;
use App\Support\Nda\NdaRoleNames;
use App\Support\ScalarValue;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;

/**
 * Streams the acceptance register as CSV. Every cell goes through `CsvCell` so a name starting with `=`, `+`, `-`,
 * `@`, a tab or a carriage return cannot run as a spreadsheet formula.
 */
final readonly class NdaAcceptanceCsvWriter
{
    private const string BYTE_ORDER_MARK = "\u{FEFF}";

    private const int CHUNK = 500;

    private const array COLUMNS = ['name', 'email', 'roles', 'version', 'status', 'accepted_at', 'accepted_roles', 'ip_address'];

    public function __construct(private NdaRegisterQuery $register, private Repository $config) {}

    /**
     * @param  resource  $stream
     */
    public function write(NdaRegisterExport $export, $stream): void
    {
        fwrite($stream, self::BYTE_ORDER_MARK);
        $this->writeRow($stream, array_map(fn (string $column): string => $this->label('nda.export.columns.'.$column, $export->locale), self::COLUMNS));

        $export->users->with('roles')->chunkById(self::CHUNK, function (Collection $users) use ($export, $stream): void {
            $acceptances = $this->register->acceptancesOf($export->version, array_values($users->map(static fn (User $user): int => $user->id)->all()));

            foreach ($users as $user) {
                /** @var User $user */
                $this->writeRow($stream, $this->row($user, $acceptances[$user->id] ?? null, $export));
            }
        });
    }

    /**
     * @return list<string>
     */
    public function row(User $user, ?TermsAcceptance $acceptance, NdaRegisterExport $export): array
    {
        $status = $acceptance instanceof TermsAcceptance ? NdaUserStatus::Accepted : NdaUserStatus::Pending;

        return [
            $user->name,
            $user->email,
            $this->roles(NdaRoleNames::of($user), $export->locale),
            $export->version->version,
            $this->label('nda.statuses.'.$status->value, $export->locale),
            $acceptance?->created_at->copy()->setTimezone($this->config->string('app.display_timezone'))->toIso8601String() ?? '',
            $this->roles($acceptance->accepted_roles ?? [], $export->locale),
            $acceptance->ip_address ?? '',
        ];
    }

    /**
     * @param  list<string>  $roles
     */
    private function roles(array $roles, string $locale): string
    {
        return implode($this->label('nda.export.role_separator', $locale), array_map(
            fn (string $role): string => $this->label(NdaRoleNames::labelKey($role), $locale),
            $roles,
        ));
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
