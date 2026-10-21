<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        $this->disableForeignKeyChecks($driver);

        // Rename core tables first.
        $this->renameTableIfExists('workspaces', 'accounts');
        $this->renameTableIfExists('workspace_users', 'account_users');
        $this->renameTableIfExists('workspace_modules', 'account_modules');
        $this->renameTableIfExists('workspace_addons', 'account_addons');
        $this->renameTableIfExists('workspace_usage', 'account_usage');
        $this->renameTableIfExists('workspace_invitations', 'account_invitations');

        $tablesWithWorkspaceId = $this->getTablesWithColumn($driver, 'workspace_id');
        $fkRules = $this->getForeignKeyRules($driver, $tablesWithWorkspaceId, 'workspace_id');

        foreach ($tablesWithWorkspaceId as $table) {
            $this->renameWorkspaceId($driver, $table);
        }

        $this->recreateAccountForeignKeys($driver, $tablesWithWorkspaceId, $fkRules);

        $this->enableForeignKeyChecks($driver);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        $this->disableForeignKeyChecks($driver);

        $tablesWithAccountId = $this->getTablesWithColumn($driver, 'account_id');

        foreach ($tablesWithAccountId as $table) {
            $this->renameAccountId($driver, $table);
        }

        $this->renameTableIfExists('account_invitations', 'workspace_invitations');
        $this->renameTableIfExists('account_usage', 'workspace_usage');
        $this->renameTableIfExists('account_addons', 'workspace_addons');
        $this->renameTableIfExists('account_modules', 'workspace_modules');
        $this->renameTableIfExists('account_users', 'workspace_users');
        $this->renameTableIfExists('accounts', 'workspaces');

        $this->enableForeignKeyChecks($driver);
    }

    private function renameTableIfExists(string $from, string $to): void
    {
        if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
            Schema::rename($from, $to);
        }
    }

    private function renameWorkspaceId(string $driver, string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, 'workspace_id') || Schema::hasColumn($table, 'account_id')) {
            return;
        }

        if ($driver === 'mysql') {
            $this->dropForeignKeysForColumn($table, 'workspace_id');
            $definition = $this->getMysqlColumnDefinition($table, 'workspace_id');
            if ($definition) {
                DB::statement("ALTER TABLE `{$table}` CHANGE `workspace_id` `account_id` {$definition}");
            }

            return;
        }

        $this->renameColumnSafe($table, 'workspace_id', 'account_id');
    }

    private function renameAccountId(string $driver, string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, 'account_id') || Schema::hasColumn($table, 'workspace_id')) {
            return;
        }

        if ($driver === 'mysql') {
            $this->dropForeignKeysForColumn($table, 'account_id');
            $definition = $this->getMysqlColumnDefinition($table, 'account_id');
            if ($definition) {
                DB::statement("ALTER TABLE `{$table}` CHANGE `account_id` `workspace_id` {$definition}");
            }

            return;
        }

        $this->renameColumnSafe($table, 'account_id', 'workspace_id');
    }

    private function getMysqlColumnDefinition(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            [$table, $column]
        );

        if (! $row) {
            return null;
        }

        $definition = $row->COLUMN_TYPE;
        $definition .= ($row->IS_NULLABLE === 'YES') ? ' NULL' : ' NOT NULL';

        if ($row->COLUMN_DEFAULT !== null) {
            $default = DB::getPdo()->quote($row->COLUMN_DEFAULT);
            $definition .= " DEFAULT {$default}";
        }

        if (! empty($row->EXTRA)) {
            $definition .= " {$row->EXTRA}";
        }

        return $definition;
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
        }
    }

    private function recreateAccountForeignKeys(string $driver, array $tables, array $fkRules): void
    {
        if ($driver !== 'mysql') {
            return;
        }

        if (! Schema::hasTable('accounts')) {
            return;
        }

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'account_id')) {
                continue;
            }

            if ($this->hasForeignKey($table, 'account_id')) {
                continue;
            }

            $rule = $fkRules[$table]['delete_rule'] ?? 'CASCADE';
            Schema::table($table, function (Blueprint $table) use ($rule) {
                $foreign = $table->foreign('account_id')->references('id')->on('accounts');
                switch (strtoupper($rule)) {
                    case 'SET NULL':
                        $foreign->nullOnDelete();
                        break;
                    case 'RESTRICT':
                        $foreign->restrictOnDelete();
                        break;
                    case 'NO ACTION':
                        $foreign->noActionOnDelete();
                        break;
                    default:
                        $foreign->cascadeOnDelete();
                        break;
                }
            });
        }
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        $row = DB::selectOne(
            'SELECT 1
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$table, $column]
        );

        return (bool) $row;
    }

    private function disableForeignKeyChecks(string $driver): void
    {
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        Schema::disableForeignKeyConstraints();
    }

    private function enableForeignKeyChecks(string $driver): void
    {
        Schema::enableForeignKeyConstraints();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function getTablesWithColumn(string $driver, string $column): array
    {
        if ($driver === 'mysql') {
            $rows = DB::select(
                'SELECT TABLE_NAME
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND COLUMN_NAME = ?',
                [$column]
            );

            return array_values(array_unique(array_map(fn ($row) => $row->TABLE_NAME, $rows)));
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            $tableNames = [];
            foreach ($rows as $row) {
                $name = $row->name ?? null;
                if ($name && Schema::hasColumn($name, $column)) {
                    $tableNames[] = $name;
                }
            }

            return $tableNames;
        }

        $tableNames = [];
        if (method_exists(Schema::getConnection()->getSchemaBuilder(), 'getAllTables')) {
            $tables = Schema::getConnection()->getSchemaBuilder()->getAllTables();
            foreach ($tables as $table) {
                $name = $this->normalizeTableName($table, $driver);
                if ($name && Schema::hasColumn($name, $column)) {
                    $tableNames[] = $name;
                }
            }
        }

        return $tableNames;
    }

    private function normalizeTableName(object|array $table, string $driver): ?string
    {
        if (is_array($table)) {
            return $table['name'] ?? null;
        }

        if (property_exists($table, 'name')) {
            return $table->name;
        }

        if ($driver === 'sqlite') {
            foreach (get_object_vars($table) as $value) {
                if (is_string($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function getForeignKeyRules(string $driver, array $tables, string $column): array
    {
        if ($driver !== 'mysql' || empty($tables)) {
            return [];
        }

        $rules = [];
        foreach ($tables as $table) {
            $row = DB::selectOne(
                'SELECT rc.DELETE_RULE
                 FROM information_schema.KEY_COLUMN_USAGE kcu
                 JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                   ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
                  AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                 WHERE kcu.TABLE_SCHEMA = DATABASE()
                   AND kcu.TABLE_NAME = ?
                   AND kcu.COLUMN_NAME = ?
                   AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                 LIMIT 1',
                [$table, $column]
            );

            if ($row && ! empty($row->DELETE_RULE)) {
                $rules[$table] = ['delete_rule' => $row->DELETE_RULE];
            }
        }

        return $rules;
    }

    private function renameColumnSafe(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
            return;
        }

        if (! class_exists(\Doctrine\DBAL\DriverManager::class)) {
            throw new RuntimeException("doctrine/dbal is required to rename columns on this driver. Please install it to rename {$table}.{$from}.");
        }

        Schema::table($table, function (Blueprint $table) use ($from, $to) {
            $table->renameColumn($from, $to);
        });
    }
};
