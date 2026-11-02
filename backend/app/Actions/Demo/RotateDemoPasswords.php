<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Data\Demo\DemoAccount;
use App\Models\Identity\User;
use App\Services\Demo\DemoAccountsDelivery;
use App\Services\Demo\DemoInstallGuard;
use App\Services\Demo\DemoPasswordRotator;
use App\Services\Demo\DemoRecords;

final readonly class RotateDemoPasswords
{
    public function __construct(
        private DemoInstallGuard $guard,
        private DemoAccountsDelivery $delivery,
        private DemoRecords $records,
        private DemoPasswordRotator $rotator,
    ) {}

    /**
     * @return list<DemoAccount>
     */
    public function handle(string $accountsFilePath): array
    {
        $this->guard->assertMayRotate();

        return $this->delivery->deliver(
            $accountsFilePath,
            fn (): array => array_values($this->records->users()->map(fn (User $user): DemoAccount => $this->rotator->rotate($user))->all()),
            /**
             * @param  list<DemoAccount>  $accounts
             * @return list<DemoAccount>
             */
            static fn (array $accounts): array => $accounts,
        );
    }
}
