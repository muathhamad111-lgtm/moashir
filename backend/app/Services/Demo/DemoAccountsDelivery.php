<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Demo\DemoAccount;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class DemoAccountsDelivery
{
    public function __construct(private Application $app, private Repository $config) {}

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $produce
     * @param  Closure(TResult): list<DemoAccount>  $accountsOf
     * @return TResult
     */
    public function deliver(string $accountsFilePath, Closure $produce, Closure $accountsOf): mixed
    {
        $file = DemoAccountsFile::create($accountsFilePath, $this->app->basePath());

        try {
            $result = DB::transaction($produce);
        } catch (Throwable $failure) {
            $file->discard();

            throw $failure;
        }

        $file->write($this->config->string('app.env'), $accountsOf($result));

        return $result;
    }
}
