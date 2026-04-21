<?php

namespace App\Console\Commands;

use App\Services\EnvironmentEditor;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CreditsoftMigrateSqliteToPostgres extends Command
{
    protected $signature = 'creditsoft:database:migrate-postgres
        {--sqlite= : Source SQLite database path}
        {--host=127.0.0.1 : PostgreSQL host}
        {--port=5432 : PostgreSQL port}
        {--database=creditsoft : PostgreSQL database}
        {--username=creditsoft : PostgreSQL username}
        {--password= : PostgreSQL password}
        {--fresh : Drop and recreate the target PostgreSQL schema before copying}
        {--force-overwrite : Truncate non-empty target tables before copying}
        {--switch-env : Update .env to use pgsql after the copy succeeds}
        {--dry-run : Report the source tables and target settings without writing}';

    protected $description = 'Copy the current CreditSoft SQLite database into a PostgreSQL office database.';

    /**
     * @var list<string>
     */
    protected array $preferredTableOrder = [
        'roles',
        'permissions',
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'clients',
        'office_billing_settings',
        'office_social_settings',
        'metric_snapshots',
        'sop_templates',
        'managed_letter_templates',
        'reporting_cycles',
        'client_documents',
        'client_provider_accounts',
        'client_billing_profiles',
        'client_payments',
        'browser_captures',
        'bureau_snapshots',
        'case_briefs',
        'case_notes',
        'letter_drafts',
        'migration_operator_captures',
        'outbound_signals',
        'sop_runs',
        'system_diagnostic_snapshots',
        'tasks',
        'tradelines',
        'user_api_keys',
        'violation_candidates',
        'audit_entries',
        'automation_discoveries',
    ];

    /**
     * @var list<string>
     */
    protected array $skipTables = [
        'migrations',
        'sqlite_sequence',
    ];

    public function handle(EnvironmentEditor $environment): int
    {
        $sqlitePath = $this->sourceSqlitePath();

        if (! File::exists($sqlitePath)) {
            $this->error("SQLite database was not found at {$sqlitePath}.");

            return self::FAILURE;
        }

        $source = 'creditsoft_sqlite_migration';
        $target = 'creditsoft_pgsql_migration';
        $targetConfig = $this->targetConfig();

        $this->configureSourceConnection($source, $sqlitePath);
        $tables = $this->orderedSourceTables($source);

        $this->info('CreditSoft SQLite to PostgreSQL migration');
        $this->line("Source: {$sqlitePath}");
        $this->line(sprintf(
            'Target: %s:%s/%s as %s',
            $targetConfig['host'],
            $targetConfig['port'],
            $targetConfig['database'],
            $targetConfig['username'],
        ));

        if ((bool) $this->option('dry-run')) {
            $this->newLine();
            $this->info('Dry run only. No PostgreSQL connection or writes were attempted.');
            $this->table(
                ['Table', 'Rows'],
                collect($tables)
                    ->map(fn (string $table): array => [$table, DB::connection($source)->table($table)->count()])
                    ->all()
            );

            return self::SUCCESS;
        }

        $this->configureTargetConnection($target, $targetConfig);

        if (! $this->testTargetConnection($target)) {
            return self::FAILURE;
        }

        if (! $this->prepareTargetSchema($target)) {
            return self::FAILURE;
        }

        foreach ($tables as $table) {
            if (! $this->copyTable($source, $target, $table)) {
                return self::FAILURE;
            }
        }

        $this->info('SQLite data was copied into PostgreSQL.');

        if ((bool) $this->option('switch-env')) {
            $environment->setMany([
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => (string) $targetConfig['host'],
                'DB_PORT' => (string) $targetConfig['port'],
                'DB_DATABASE' => (string) $targetConfig['database'],
                'DB_USERNAME' => (string) $targetConfig['username'],
                'DB_PASSWORD' => (string) $targetConfig['password'],
                'DB_SSLMODE' => 'prefer',
            ]);

            $this->info('.env now points CreditSoft at PostgreSQL.');
        } else {
            $this->warn('CreditSoft is not switched yet. Re-run with --switch-env after verifying the PostgreSQL copy.');
        }

        return self::SUCCESS;
    }

    protected function sourceSqlitePath(): string
    {
        $option = trim((string) ($this->option('sqlite') ?? ''));

        if ($option !== '') {
            return $option;
        }

        return database_path('database.sqlite');
    }

    /**
     * @return array{driver:string,host:string,port:string,database:string,username:string,password:string,charset:string,prefix:string,prefix_indexes:bool,search_path:string,sslmode:string}
     */
    protected function targetConfig(): array
    {
        return [
            'driver' => 'pgsql',
            'host' => trim((string) $this->option('host')) ?: '127.0.0.1',
            'port' => trim((string) $this->option('port')) ?: '5432',
            'database' => trim((string) $this->option('database')) ?: 'creditsoft',
            'username' => trim((string) $this->option('username')) ?: 'creditsoft',
            'password' => (string) ($this->option('password') ?? env('CREDITSOFT_PG_PASSWORD', env('DB_PASSWORD', ''))),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ];
    }

    protected function configureSourceConnection(string $name, string $sqlitePath): void
    {
        config([
            "database.connections.{$name}" => [
                'driver' => 'sqlite',
                'database' => $sqlitePath,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge($name);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function configureTargetConnection(string $name, array $config): void
    {
        config(["database.connections.{$name}" => $config]);

        DB::purge($name);
    }

    /**
     * @return list<string>
     */
    protected function orderedSourceTables(string $source): array
    {
        $rows = DB::connection($source)->select(
            "select name from sqlite_master where type = 'table' order by name"
        );

        $tables = collect($rows)
            ->map(fn (object $row): string => (string) ($row->name ?? ''))
            ->filter(fn (string $table): bool => $table !== '' && ! in_array($table, $this->skipTables, true))
            ->values();

        $known = collect($this->preferredTableOrder)
            ->filter(fn (string $table): bool => $tables->contains($table));

        $remaining = $tables
            ->reject(fn (string $table): bool => $known->contains($table))
            ->values();

        return $known->merge($remaining)->values()->all();
    }

    protected function testTargetConnection(string $target): bool
    {
        try {
            $version = DB::connection($target)->selectOne('select version() as version');
            $this->line('PostgreSQL connection OK: '.Str($version->version ?? 'PostgreSQL')->limit(90));

            return true;
        } catch (Throwable $throwable) {
            $this->error('Could not connect to PostgreSQL: '.$throwable->getMessage());

            return false;
        }
    }

    protected function prepareTargetSchema(string $target): bool
    {
        $command = (bool) $this->option('fresh') ? 'migrate:fresh' : 'migrate';
        $this->info("Running {$command} on PostgreSQL target.");

        $exitCode = Artisan::call($command, [
            '--database' => $target,
            '--force' => true,
        ]);

        $output = trim(Artisan::output());

        if ($output !== '') {
            $this->line($output);
        }

        if ($exitCode !== self::SUCCESS) {
            $this->error("{$command} failed on PostgreSQL target.");

            return false;
        }

        return true;
    }

    protected function copyTable(string $source, string $target, string $table): bool
    {
        if (! Schema::connection($target)->hasTable($table)) {
            $this->warn("Skipping {$table}; target table does not exist.");

            return true;
        }

        $sourceRows = DB::connection($source)->table($table)->count();

        if ($sourceRows === 0) {
            $this->line("Skipping {$table}; source table is empty.");

            return true;
        }

        $targetRows = DB::connection($target)->table($table)->count();

        if ($targetRows > 0) {
            if (! (bool) $this->option('force-overwrite')) {
                $this->error("Target table {$table} already has {$targetRows} rows. Use --fresh or --force-overwrite.");

                return false;
            }

            DB::connection($target)->statement(
                'truncate table '.$this->quoteIdentifier($table).' restart identity cascade'
            );
        }

        $sourceColumns = Schema::connection($source)->getColumnListing($table);
        $columns = Schema::connection($target)->getColumnListing($table);
        $columnMap = array_flip($columns);
        $copied = 0;
        $query = DB::connection($source)->table($table);

        if (in_array('id', $sourceColumns, true)) {
            $query->orderBy('id');
        } elseif (($sourceColumns[0] ?? '') !== '') {
            $query->orderBy($sourceColumns[0]);
        }

        $query->chunk(500, function ($rows) use ($target, $table, $columnMap, &$copied): void {
            $batch = [];

            foreach ($rows as $row) {
                $batch[] = Arr::where(
                    array_intersect_key((array) $row, $columnMap),
                    fn (mixed $value): bool => $value !== null || true
                );
            }

            if ($batch !== []) {
                $beforeCount = DB::connection($target)->table($table)->count();
                DB::connection($target)->table($table)->insertOrIgnore($batch);
                $copied += max(0, DB::connection($target)->table($table)->count() - $beforeCount);
            }
        });

        $this->line("Copied {$copied} rows into {$table}.");
        $this->resetPostgresSequence($target, $table, $columns);

        return true;
    }

    /**
     * @param  list<string>  $columns
     */
    protected function resetPostgresSequence(string $target, string $table, array $columns): void
    {
        if (! in_array('id', $columns, true)) {
            return;
        }

        try {
            $sequence = DB::connection($target)->selectOne(
                "select pg_get_serial_sequence(?, 'id') as sequence",
                [$table],
            );
            $sequenceName = (string) ($sequence->sequence ?? '');

            if ($sequenceName === '') {
                return;
            }

            $maxId = (int) DB::connection($target)->table($table)->max('id');
            DB::connection($target)->statement(
                'select setval(?, ?, ?)',
                [$sequenceName, max($maxId, 1), $maxId > 0],
            );
        } catch (Throwable $throwable) {
            $this->warn("Could not reset sequence for {$table}: ".$throwable->getMessage());
        }
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
