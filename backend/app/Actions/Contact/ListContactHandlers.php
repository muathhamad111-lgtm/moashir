<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Models\Identity\User;
use App\Services\Contact\ContactHandlers;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListContactHandlers
{
    public function __construct(private ContactHandlers $handlers) {}

    /**
     * @return Collection<int, User>
     */
    public function handle(): Collection
    {
        return $this->handlers->query()->orderBy('name')->get(['id', 'name']);
    }
}
