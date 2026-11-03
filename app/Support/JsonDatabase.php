<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Db;
use App\Core\Env;
use RuntimeException;

/** Durable JSON snapshots grouped by domain; SQLite is only the request cache. */
final class JsonDatabase
{
    private static ?string $baseRevision = null;

    /** @var list<string> */
    private const TABLES = [
        'roles', 'permissions', 'role_permissions', 'users', 'departments',
        'categories', 'industries', 'locations', 'suppliers', 'items', 'stock',
        'stock_positions', 'stock_movements', 'requests', 'request_items',
        'request_status_history', 'approval_rules', 'approvals', 'deliveries', 'delivery_items',
        'events', 'event_allocations', 'notifications', 'audit_log', 'settings',
        'login_attempts', 'password_resets', 'idempotency_keys', 'sequences', 'stock_exit_orders',
    ];

    /** @var array<string,list<string>> */
    private const GROUPS = [
        'access.json' => ['roles', 'permissions', 'role_permissions', 'users', 'departments', 'industries'],
        'catalog.json' => ['categories', 'locations', 'suppliers', 'items'],
        'inventory.json' => ['stock', 'stock_positions', 'stock_movements', 'stock_exit_orders'],
        'requests.json' => ['requests', 'request_items', 'request_status_history', 'approval_rules', 'approvals'],
        'deliveries.json' => ['deliveries', 'delivery_items'],
        'events.json' => ['events', 'event_allocations'],
        'system.json' => ['notifications', 'audit_log', 'settings', 'login_attempts', 'password_resets', 'idempotency_keys', 'sequences'],
    ];

    public static function directory(): string
    {
        $configured = (string) (getenv('JSON_DB_PATH') ?: '');
        $default = Env::get('VERCEL')
            ? sys_get_temp_dir() . '/brindes/storage/json'
            : BASE_PATH . '/storage/json';
        return rtrim($configured !== '' ? $configured : $default, '/\\');
    }

    /** Return the JSON domain files that contain one or more changed tables. */
    public static function documentsForTables(?array $tables = null): array
    {
        if ($tables === null || $tables === [] || in_array('*', $tables, true)) {
            return array_keys(self::GROUPS);
        }
        $unknown = array_diff($tables, self::TABLES);
        if ($unknown !== []) {
            return array_keys(self::GROUPS);
        }

        $changed = array_fill_keys($tables, true);
        $files = [];
        foreach (self::GROUPS as $file => $groupTables) {
            foreach ($groupTables as $table) {
                if (isset($changed[$table])) {
                    $files[] = $file;
                    break;
                }
            }
        }
        return $files === [] ? array_keys(self::GROUPS) : $files;
    }

    /** Export known tables into local inspection files and private Blob snapshots. */
    public static function mirrorFromPdo(?array $changedTables = null): void
    {
        self::ensureDirectory();
        $store = null;
        if (Env::get('VERCEL')) {
            $token = Env::get('BLOB_READ_WRITE_TOKEN', '');
            if (!is_string($token) || $token === '') {
                throw new RuntimeException('Persistência JSON indisponível: BLOB_READ_WRITE_TOKEN não está configurado.');
            }
            $storeId = Env::get('BLOB_STORE_ID', '');
            if (!is_string($storeId) || $storeId === '') {
                throw new RuntimeException('Persistência JSON indisponível: BLOB_STORE_ID não está configurado.');
            }
            $store = new VercelJsonStore($token, null, $storeId);
            if (!$store->hasSnapshot()) {
                $changedTables = null;
            }
        }
        $files = self::documentsForTables($changedTables);
        $selected = [];
        foreach ($files as $file) {
            foreach (self::GROUPS[$file] as $table) {
                $selected[$table] = $file;
            }
        }

        $documents = [];
        foreach (self::TABLES as $table) {
            if (!isset($selected[$table])) {
                continue;
            }
            try {
                $rows = Db::fetchAll('SELECT * FROM `' . $table . '`');
            } catch (\Throwable $error) {
                // Never publish an empty snapshot when a source table could not be read.
                throw new RuntimeException('JSON snapshot interrompido ao ler a tabela ' . $table . '.', 0, $error);
            }

            $localRows = self::localSafeRows($table, $rows);
            self::write($table, $localRows);
            $documents[$selected[$table]] ??= [
                'schema_version' => 1,
                'generated_at' => date(DATE_ATOM),
                'tables' => [],
            ];
            $documents[$selected[$table]]['tables'][$table] = self::privateSnapshotRows($table, $rows);
        }

        if ($store !== null) {
            self::rememberRevision($store->save($documents, self::$baseRevision));
        }
    }

    /** Restore the last complete Blob revision into the per-invocation SQLite cache. */
    public static function restoreFromBlob(): bool
    {
        if (!Env::get('VERCEL')) {
            return false;
        }
        $token = Env::get('BLOB_READ_WRITE_TOKEN', '');
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Persistência JSON indisponível: BLOB_READ_WRITE_TOKEN não está configurado.');
        }
        $storeId = Env::get('BLOB_STORE_ID', '');
        if (!is_string($storeId) || $storeId === '') {
            throw new RuntimeException('Persistência JSON indisponível: BLOB_STORE_ID não está configurado.');
        }
        if (!Db::isSqlite()) {
            throw new RuntimeException('A restauração JSON exige o cache SQLite do runtime Vercel.');
        }

        $dbPath = (string) \App\Core\Config::get('db.path');
        $knownRevision = self::localSnapshotRevision($dbPath);
        $snapshot = (new VercelJsonStore($token, null, $storeId))->loadSnapshot($knownRevision);
        if ($snapshot === null) {
            self::invalidateLocalRevision();
            return false;
        }
        if ($snapshot['documents'] === null) {
            self::$baseRevision = $snapshot['revision'];
            return true;
        }
        self::restoreSnapshot($snapshot['documents'], $snapshot['revision']);
        return true;
    }

    public static function restoreSnapshot(array $documents, string $revision): void
    {
        $expectedFiles = array_keys(self::GROUPS);
        $actualFiles = array_keys($documents);
        sort($expectedFiles);
        sort($actualFiles);
        if ($actualFiles !== $expectedFiles) {
            throw new RuntimeException('Snapshot JSON deve conter exatamente os sete domínios esperados.');
        }
        $tableRows = [];
        foreach (self::GROUPS as $file => $tables) {
            $document = $documents[$file] ?? null;
            if (!is_array($document) || (int) ($document['schema_version'] ?? 0) !== 1 || !is_array($document['tables'] ?? null)) {
                throw new RuntimeException('Snapshot JSON incompleto ou inválido: ' . $file);
            }
            foreach ($tables as $table) {
                $rows = $document['tables'][$table] ?? null;
                if (!is_array($rows)) {
                    throw new RuntimeException('Tabela ausente no snapshot JSON: ' . $table);
                }
                $tableRows[$table] = $rows;
                $schemaRows = Db::fetchAll('PRAGMA table_info(' . $table . ')');
                $schemaColumns = array_fill_keys(array_map(static fn (array $column): string => (string) ($column['name'] ?? ''), $schemaRows), true);
                if ($schemaColumns === []) {
                    throw new RuntimeException('Tabela indisponível no cache SQLite: ' . $table);
                }
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        throw new RuntimeException('Linha inválida no snapshot JSON: ' . $table);
                    }
                    $unknownColumns = array_diff(array_keys($row), array_keys($schemaColumns));
                    if ($unknownColumns !== []) {
                        throw new RuntimeException('Coluna não permitida no snapshot JSON: ' . $table . '.' . (string) reset($unknownColumns));
                    }
                }
            }
        }

        $pdo = Db::pdo();
        $pdo->exec('PRAGMA foreign_keys = OFF');
        try {
            Db::transaction(static function () use ($tableRows): void {
                foreach (array_reverse(self::TABLES) as $table) {
                    Db::query('DELETE FROM `' . $table . '`');
                }
                foreach (self::TABLES as $table) {
                    foreach ($tableRows[$table] as $row) {
                        if (!is_array($row)) {
                            throw new RuntimeException('Linha inválida no snapshot JSON: ' . $table);
                        }
                        if ($row === []) {
                            continue;
                        }
                        foreach (array_keys($row) as $column) {
                            if (!is_string($column) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
                                throw new RuntimeException('Coluna inválida no snapshot JSON: ' . $table);
                            }
                        }
                        $columns = array_keys($row);
                        $quoted = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
                        $values = implode(', ', array_fill(0, count($columns), '?'));
                        Db::query('INSERT INTO `' . $table . '` (' . $quoted . ') VALUES (' . $values . ')', array_values($row));
                    }
                }
                $foreignKeyViolations = Db::fetchAll('PRAGMA foreign_key_check');
                if ($foreignKeyViolations !== []) {
                    $violation = $foreignKeyViolations[0];
                    throw new RuntimeException(
                        'Snapshot JSON viola uma chave estrangeira na tabela ' . (string) ($violation['table'] ?? 'desconhecida') . '.'
                    );
                }
            });
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        self::rememberRevision($revision);
        Db::clearMutationState();
        return;
    }

    public static function invalidateLocalRevision(): void
    {
        self::$baseRevision = null;
        $path = self::revisionPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Return the remote revision only when the local SQLite cache still matches its saved hash. */
    public static function localSnapshotRevision(string $dbPath): ?string
    {
        if ($dbPath === '' || !is_file($dbPath)) {
            return null;
        }
        $path = self::revisionPath();
        if (!is_file($path)) {
            return null;
        }
        $marker = json_decode((string) file_get_contents($path), true);
        if (!is_array($marker)) {
            return null;
        }
        $revision = $marker['revision'] ?? null;
        $expectedHash = $marker['sqlite_fingerprint'] ?? null;
        $actualHash = self::databaseFingerprint($dbPath);
        if (!is_string($revision) || $revision === '' || !is_string($expectedHash) || !is_string($actualHash)) {
            return null;
        }
        return hash_equals($expectedHash, $actualHash) ? $revision : null;
    }

    private static function databaseFingerprint(string $dbPath): string|false
    {
        if ($dbPath === '' || !is_file($dbPath)) {
            return false;
        }
        $context = hash_init('sha256');
        foreach ([$dbPath, $dbPath . '-wal', $dbPath . '-shm'] as $file) {
            $hash = is_file($file) ? hash_file('sha256', $file) : 'missing';
            if (!is_string($hash)) {
                return false;
            }
            hash_update($context, basename($file) . "\\0" . $hash . "\\0");
        }
        return hash_final($context);
    }

    private static function rememberRevision(string $revision): void
    {
        self::ensureDirectory();
        $dbPath = (string) \App\Core\Config::get('db.path');
        $databaseFingerprint = self::databaseFingerprint($dbPath);
        if (!is_string($databaseFingerprint)) {
            throw new RuntimeException('Não foi possível calcular a impressão digital do cache SQLite.');
        }
        $path = self::revisionPath();
        $tmp = $path . '.tmp';
        $marker = json_encode(['revision' => $revision, 'sqlite_fingerprint' => $databaseFingerprint], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        if (file_put_contents($tmp, $marker, LOCK_EX) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Não foi possível registrar a revisão JSON local.');
        }
        self::$baseRevision = $revision;
    }

    private static function revisionPath(): string
    {
        return self::directory() . '/.snapshot-revision';
    }

    /** Reset only the local JSON mirror, never the remote snapshot or app data. */
    public static function reset(): void
    {
        self::ensureDirectory();
        self::invalidateLocalRevision();
        foreach (self::TABLES as $table) {
            self::write($table, []);
        }
        self::write('_manifest', [[
            'generated_at' => date(DATE_ATOM),
            'reset_at' => date(DATE_ATOM),
            'timezone' => date_default_timezone_get(),
            'source' => 'manual local JSON reset',
            'tables' => self::TABLES,
        ]]);
    }

    /** @return list<array<string,mixed>> */
    public static function read(string $table): array
    {
        self::assertTable($table);
        $path = self::path($table);
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    private static function localSafeRows(string $table, array $rows): array
    {
        return array_map(static function (array $row) use ($table): array {
            if ($table === 'users') {
                unset($row['password_hash']);
            }
            if ($table === 'settings' && isset($row['key']) && in_array((string) $row['key'], ['app.key', 'APP_KEY'], true)) {
                $row['value'] = '[REDACTED]';
            }
            return $row;
        }, $rows);
    }

    private static function privateSnapshotRows(string $table, array $rows): array
    {
        if ($table !== 'settings') {
            return $rows;
        }
        return array_values(array_filter($rows, static fn (array $row): bool =>
            !isset($row['key']) || !in_array((string) $row['key'], ['app.key', 'APP_KEY'], true)
        ));
    }

    private static function write(string $table, array $rows): void
    {
        $path = self::path($table);
        $json = json_encode($rows, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Não foi possível gravar o espelho JSON: ' . $path);
        }
    }

    private static function path(string $table): string
    {
        self::assertTable($table);
        return self::directory() . '/' . $table . '.json';
    }

    private static function ensureDirectory(): void
    {
        if (!is_dir(self::directory()) && !mkdir(self::directory(), 0775, true) && !is_dir(self::directory())) {
            throw new RuntimeException('Não foi possível criar o diretório JSON.');
        }
    }

    private static function assertTable(string $table): void
    {
        if ($table !== '_manifest' && !in_array($table, self::TABLES, true)) {
            throw new RuntimeException('Tabela JSON não autorizada: ' . $table);
        }
    }
}
