<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\Identity\User;

final class SaveTableColumns
{
    /**
     * Keeps the columns a user chose for each table; a table set to null goes back to its defaults.
     *
     * @param  array<string, list<string>|null>  $choices
     */
    public function handle(User $user, array $choices): void
    {
        foreach ($choices as $table => $columns) {
            if ($columns === null) {
                $user->tableColumns()->where('table_key', $table)->delete();

                continue;
            }

            $user->tableColumns()->updateOrCreate(['table_key' => $table], ['columns' => array_values($columns)]);
        }
    }
}
