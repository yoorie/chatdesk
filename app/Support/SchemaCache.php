<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class SchemaCache
{
    protected static array $tables = [];

    public static function hasTable(string $table): bool
    {
        return self::$tables[$table] ??= Schema::hasTable($table);
    }

    public static function flush(): void
    {
        self::$tables = [];
    }
}
