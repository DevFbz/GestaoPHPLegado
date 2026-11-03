<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Thin PDO wrapper: prepared statements only, nested transactions, helpers.
 * Table/column names passed to insert()/update() must come from code, never from user input.
 */
final class Db
{
    private static ?PDO $pdo = null;
    private static int $depth = 0;
    private static bool $mutated = false;
    /** @var array<string,true> */
    private static array $mutatedTables = [];

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            if (self::isSqlite()) {
                $path = (string) Config::get('db.path');
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                self::$pdo = new PDO('sqlite:' . $path, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                self::$pdo->exec(getenv('VERCEL') ? 'PRAGMA journal_mode = DELETE' : 'PRAGMA journal_mode = WAL');
                self::$pdo->exec('PRAGMA busy_timeout = 5000');
            } else {
                $c = Config::get('db');
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']);
                self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]);
                $offset = (new \DateTimeImmutable('now'))->format('P');
                self::$pdo->exec("SET time_zone = '{$offset}'");
                self::$pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            }
        }
        return self::$pdo;
    }

    public static function isSqlite(): bool
    {
        return (string) Config::get('db.driver', 'mysql') === 'sqlite';
    }

    /** Drop the connection (tests / child processes). */
    public static function disconnect(): void
    {
        self::$pdo = null;
        self::$depth = 0;
        self::clearMutationState();
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $sql = self::adapt($sql);
        $attempt = 0;
        while (true) {
            try {
                $stmt = self::pdo()->prepare($sql);
                foreach ($params as $key => $value) {
                    $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
                    $type = match (true) {
                        is_int($value) => PDO::PARAM_INT,
                        is_bool($value) => PDO::PARAM_INT,
                        $value === null => PDO::PARAM_NULL,
                        default => PDO::PARAM_STR,
                    };
                    $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
                }
                $stmt->execute();
                break;
            } catch (PDOException $e) {
                $locked = self::isSqlite() && str_contains(strtolower($e->getMessage()), 'database is locked');
                if (!$locked || $attempt >= 5) {
                    throw $e;
                }
                usleep(50000 * (2 ** $attempt));
                $attempt++;
            }
        }
        $readOnly = preg_match('/^\s*(SELECT|SHOW|DESCRIBE|PRAGMA|EXPLAIN)\b/i', $sql) === 1;
        $command = strtoupper(strtok(trim($sql), chr(32) . chr(9) . chr(10) . chr(13)));
        $dml = in_array($command, ['INSERT', 'REPLACE', 'UPDATE', 'DELETE'], true);
        // No-op DML (especially DELETEs during idempotent bootstrap) changes no persisted state;
        // treating it as a mutation makes every read request race to rewrite the Blob snapshot.
        if (!$readOnly && (!$dml || $stmt->rowCount() > 0)) {
            self::$mutated = true;
            self::trackMutatedTable($sql);
        }
        return $stmt;
    }

    private static function trackMutatedTable(string $sql): void
    {
        $pattern = '/^\\s*(?:INSERT(?:\\s+OR\\s+\\w+)?\\s+INTO|REPLACE\\s+INTO|UPDATE|DELETE\\s+FROM|CREATE\\s+TABLE(?:\\s+IF\\s+NOT\\s+EXISTS)?|ALTER\\s+TABLE|DROP\\s+TABLE(?:\\s+IF\\s+EXISTS)?|TRUNCATE\\s+TABLE)\\s+[`"]?([A-Za-z_][A-Za-z0-9_]*)/i';
        if (preg_match($pattern, $sql, $match)) {
            self::$mutatedTables[strtolower($match[1])] = true;
            return;
        }
        self::$mutatedTables['*'] = true;
    }

    /** @return list<string> */
    public static function mutatedTables(): array
    {
        return array_keys(self::$mutatedTables);
    }

    public static function clearMutationState(): void
    {
        self::$mutated = false;
        self::$mutatedTables = [];
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn ($c) => "`{$c}`", $columns)),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        self::query($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    /** @param array $where column => value (AND) */
    public static function update(string $table, array $data, array $where): int
    {
        if ($data === []) {
            return 0;
        }
        $set = implode(', ', array_map(fn ($c) => "`{$c}` = ?", array_keys($data)));
        $cond = implode(' AND ', array_map(fn ($c) => "`{$c}` = ?", array_keys($where)));
        $stmt = self::query("UPDATE `{$table}` SET {$set} WHERE {$cond}", [...array_values($data), ...array_values($where)]);
        return $stmt->rowCount();
    }

    /**
     * Run $fn inside a transaction. SQLite homologation retries transient file locks;
     * MySQL/MariaDB keeps the normal row-lock behavior.
     */
    public static function transaction(callable $fn): mixed
    {
        if (self::isSqlite() && self::$depth === 0) {
            $attempt = 0;
            while (true) {
                try {
                    return self::transactionOnce($fn);
                } catch (PDOException $e) {
                    $locked = str_contains(strtolower($e->getMessage()), 'database is locked');
                    if (!$locked || $attempt >= 5) {
                        throw $e;
                    }
                    usleep(100000 * (2 ** $attempt));
                    $attempt++;
                }
            }
        }
        return self::transactionOnce($fn);
    }

    private static function transactionOnce(callable $fn): mixed
    {
        $pdo = self::pdo();
        if (self::$depth === 0) {
            $pdo->beginTransaction();
        }
        self::$depth++;
        try {
            $result = $fn();
            self::$depth--;
            if (self::$depth === 0) {
                $pdo->commit();
                if (Env::get('JSON_DB_MIRROR', false) === true && !Env::get('VERCEL')) {
                    try {
                        \App\Support\JsonDatabase::mirrorFromPdo(self::mutatedTables());
                    } catch (Throwable $mirrorError) {
                        Log::error('JSON mirror failed', ['error' => $mirrorError->getMessage()]);
                    }
                }
            }
            return $result;
        } catch (Throwable $e) {
            self::$depth--;
            if (self::$depth === 0 && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function inTransaction(): bool
    {
        return self::$depth > 0;
    }

    public static function isDuplicateKey(Throwable $e): bool
    {
        if (!$e instanceof PDOException) {
            return false;
        }
        $code = (int) ($e->errorInfo[1] ?? 0);
        return $code === 1062 || $code === 19;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        if (self::isSqlite()) {
            $rows = self::fetchAll('PRAGMA table_info(' . $table . ')');
            foreach ($rows as $row) {
                if (strcasecmp((string) ($row['name'] ?? ''), $column) === 0) {
                    return true;
                }
            }
            return false;
        }
        return self::fetch("SHOW COLUMNS FROM `{$table}` LIKE ?", [$column]) !== null;
    }

    private static function adapt(string $sql): string
    {
        if (!self::isSqlite()) {
            return $sql;
        }
        $sql = str_replace(' FOR UPDATE', '', $sql);
        $sql = preg_replace("/DATE_FORMAT\(([^,]+),\s*'%Y-%m'\)/i", "strftime('%Y-%m', $1)", $sql) ?? $sql;
        $sql = preg_replace('/GREATEST\(([^,]+),\s*([^)]+)\)/i', '(CASE WHEN ($1) > ($2) THEN ($1) ELSE ($2) END)', $sql) ?? $sql;
        return $sql;
    }

    /** Persist changed JSON domain snapshots before acknowledging writes on Vercel. */
    public static function persistDemo(): void
    {
        if (!self::$mutated || !self::isSqlite() || !Env::get('VERCEL')) {
            return;
        }
        try {
            $hasUsers = false;
            try {
                $hasUsers = in_array('users', \App\Support\Installer::tables(), true);
            } catch (Throwable) {
                $hasUsers = false;
            }
            if (!$hasUsers) {
                return;
            }
            // SQLite is an ephemeral request cache; JSON in private Blob is durable.
            \App\Support\JsonDatabase::mirrorFromPdo(self::mutatedTables());
            self::clearMutationState();
        } catch (Throwable $e) {
            Log::error('JSON snapshot persist failed', ['error' => $e->getMessage()]);
            \App\Support\JsonDatabase::invalidateLocalRevision();
            self::clearMutationState();
            throw $e;
        }
    }

    /** Escape a value for use inside LIKE '%...%'. */
    public static function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
