<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Connection;

final readonly class TableByTableSchemaWipe
{
    public function __construct(private Connection $connection) {}

    public function handle(): void
    {
        $schema = $this->connection->getSchemaBuilder();
        $grammar = $this->connection->getSchemaGrammar();

        foreach ($schema->getTableListing($schema->getCurrentSchemaListing()) as $table) {
            $this->connection->statement('drop table if exists '.$grammar->wrapTable($table).' cascade');
        }
    }
}
