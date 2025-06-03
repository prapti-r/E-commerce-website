<?php

namespace Yajra\Oci8\Schema;

use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\OracleReservedWords;

class Trigger
{
    use OracleReservedWords;

    public function __construct(protected Oci8Connection $connection) {}

    public function autoIncrement(string $table, string $column, string $triggerName, string $sequenceName): bool
    {
        if ($this->connection->getSchemaPrefix()) {
            $table = $this->connection->withSchemaPrefix($table);
            $triggerName = $this->connection->withSchemaPrefix($sequenceName);
            $sequenceName = $this->connection->withSchemaPrefix($sequenceName);
        }

        $grammar = $this->connection->getQueryGrammar();

        $table = $grammar->wrapTable($table);
        $column = $grammar->wrap($column);

        return $this->connection->statement("
            create trigger $triggerName
            before insert on {$table}
            for each row
                begin
            if :new.{$column} is null then
                select {$sequenceName}.nextval into :new.{$column} from dual;
            end if;
            end;");
    }

    public function drop(string $name): bool
    {
        if (! $name) {
            return false;
        }

        return $this->connection->statement("declare
                e exception;
                pragma exception_init(e,-4080);
            begin
                execute immediate 'drop trigger {$name}';
            exception
            when e then
                null;
            end;");
    }
}
