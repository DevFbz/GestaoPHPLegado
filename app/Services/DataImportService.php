<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Core\HttpException;
use App\Support\Installer;
use PDOException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final class DataImportService
{
    private const MAX_FILE_BYTES = 26214400;

    private const EXCLUDED_TABLES = [
        'users', 'roles', 'permissions', 'role_permissions', 'login_attempts',
        'password_resets', 'idempotency_keys',
    ];

    private const SENSITIVE_COLUMNS = [
        'password_hash', 'session_version', 'token_hash', 'reset_token', 'csrf_token',
        'last_error', 'api_key', 'secret', 'access_token', 'refresh_token',
    ];

    private const USER_REFERENCE_COLUMNS = [
        'user_id', 'requester_id', 'created_by', 'updated_by', 'deleted_by',
        'approver_user_id', 'delivered_by', 'authorized_by', 'confirmed_by',
    ];

    public static function isAvailable(): bool
    {
        $dataDir = getenv('OFFLINE_DATA_DIR');
        $vercel = getenv('VERCEL');
        return Db::isSqlite()
            && $dataDir !== false
            && trim($dataDir) !== ''
            && ($vercel === false || $vercel === '');
    }

    public static function preview(array $file): array
    {
        $workbook = self::readWorkbook($file);
        $schema = self::schema(array_keys($workbook['tables']));
        $tables = [];
        $newRows = 0;
        $existingRows = 0;

        foreach ($workbook['tables'] as $table => $tableData) {
            if (in_array($table, self::EXCLUDED_TABLES, true)) {
                continue;
            }
            $added = 0;
            $existing = 0;
            foreach ($tableData['rows'] as $row) {
                if (self::findByPrimaryKey($table, $row, $schema[$table]) !== null) {
                    $existing++;
                } else {
                    $added++;
                }
            }
            $tables[$table] = ['new' => $added, 'already_present' => $existing];
            $newRows += $added;
            $existingRows += $existing;
        }

        return [
            'new_rows' => $newRows,
            'already_present_rows' => $existingRows,
            'excluded_tables' => $workbook['excluded_tables'],
            'tables' => $tables,
        ];
    }

    public static function import(array $file): array
    {
        self::ensureAvailable();
        $workbook = self::readWorkbook($file);
        $schema = self::schema(array_keys($workbook['tables']));
        $order = self::tableOrder(array_keys($workbook['tables']), $schema);
        $userMap = self::mapUsers($workbook['users']);

        return Db::transaction(function () use ($workbook, $schema, $order, $userMap): array {
            $mapped = ['users' => ['id' => $userMap]];
            $tables = [];
            $insertedRows = 0;
            $skippedRows = 0;

            foreach ($order as $table) {
                if (in_array($table, self::EXCLUDED_TABLES, true)) {
                    continue;
                }
                $tables[$table] = ['inserted' => 0, 'skipped' => 0, 'missing_reference' => 0, 'conflict' => 0];
                foreach ($workbook['tables'][$table]['rows'] as $sourceRow) {
                    $row = self::mapReferences($table, $sourceRow, $schema[$table], $mapped, $workbook['tables']);
                    if ($row === null) {
                        self::countSkipped($tables[$table], $skippedRows, 'missing_reference');
                        continue;
                    }

                    $existing = self::findByPrimaryKey($table, $row, $schema[$table]);
                    if ($existing !== null) {
                        if (self::sameImportedValues($row, $existing)) {
                            self::rememberMappings($table, $sourceRow, $existing, $mapped);
                        } else {
                            self::countSkipped($tables[$table], $skippedRows, 'conflict');
                        }
                        continue;
                    }

                    try {
                        $columns = array_keys($row);
                        $quoted = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
                        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                        $statement = Db::query(
                            'INSERT INTO `' . $table . '` (' . $quoted . ') VALUES (' . $placeholders . ')',
                            array_values($row)
                        );
                        if ($statement->rowCount() > 0) {
                            $inserted = self::findByPrimaryKey($table, $row, $schema[$table]);
                            if ($inserted !== null) {
                                self::rememberMappings($table, $sourceRow, $inserted, $mapped);
                            }
                            $tables[$table]['inserted']++;
                            $insertedRows++;
                        } else {
                            self::countSkipped($tables[$table], $skippedRows, 'conflict');
                        }
                    } catch (PDOException $error) {
                        if (!self::isConstraintViolation($error)) {
                            throw $error;
                        }
                        $reason = self::isUniqueConstraintViolation($error) ? 'conflict' : 'missing_reference';
                        self::countSkipped($tables[$table], $skippedRows, $reason);
                    }
                }
            }

            return [
                'inserted_rows' => $insertedRows,
                'skipped_rows' => $skippedRows,
                'excluded_tables' => $workbook['excluded_tables'],
                'tables' => $tables,
            ];
        });
    }

    private static function ensureAvailable(): void
    {
        if (!self::isAvailable()) {
            throw HttpException::notFound('A importação completa está disponível apenas no executável offline.');
        }
    }

    /** @return array{tables:array<string,array{rows:list<array<string,mixed>>}>,users:list<array<string,mixed>>,excluded_tables:list<string>} */
    private static function readWorkbook(array $file): array
    {
        self::ensureAvailable();
        $path = (string) ($file['tmp_name'] ?? '');
        if ($path === '' || !is_file($path) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw HttpException::validation(['file' => 'Selecione o arquivo Excel exportado pelo sistema.']);
        }
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > self::MAX_FILE_BYTES) {
            throw HttpException::validation(['file' => 'O arquivo está vazio ou excede o limite de 25 MB.']);
        }
        try {
            if (IOFactory::identify($path) !== 'Xlsx') {
                throw HttpException::validation(['file' => 'Use o arquivo .xlsx exportado pelo sistema.']);
            }
            $book = IOFactory::load($path);
        } catch (HttpException $error) {
            throw $error;
        } catch (Throwable) {
            throw HttpException::validation(['file' => 'Não foi possível ler a planilha. Selecione uma exportação completa válida.']);
        }

        $knownTables = array_flip(Installer::tables());
        $tables = [];
        $users = [];
        $excluded = [];
        try {
            foreach ($book->getAllSheets() as $sheet) {
                $table = strtolower(trim($sheet->getTitle()));
                if (!isset($knownTables[$table]) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
                    continue;
                }
                if ($table !== 'users' && in_array($table, self::EXCLUDED_TABLES, true)) {
                    $excluded[] = $table;
                    continue;
                }
                $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
                $headers = [];
                for ($column = 1; $column <= $lastColumn; $column++) {
                    $value = $sheet->getCell([$column, 1])->getValue();
                    $headers[] = is_scalar($value) ? trim((string) $value) : '';
                }
                if ($headers === [] || count(array_filter($headers, static fn (string $header): bool => $header !== '')) === 0) {
                    continue;
                }
                $tableSchema = self::schema([$table])[$table];
                $columnNames = array_column($tableSchema['columns'], 'name');
                $seen = [];
                foreach ($headers as $header) {
                    if ($header === '') {
                        continue;
                    }
                    if (isset($seen[$header]) || !in_array($header, $columnNames, true)) {
                        throw HttpException::validation(['file' => "A aba {$table} contém colunas duplicadas ou incompatíveis."]);
                    }
                    if (in_array(strtolower($header), self::SENSITIVE_COLUMNS, true)) {
                        throw HttpException::validation(['file' => 'A planilha contém campos de autenticação ou segredos. Exporte novamente pelo sistema.']);
                    }
                    $seen[$header] = true;
                }
                if ($table !== 'users') {
                    foreach ($tableSchema['columns'] as $column) {
                        if ((int) $column['notnull'] === 1 && $column['dflt_value'] === null && (int) $column['pk'] === 0
                            && !in_array($column['name'], $headers, true)) {
                            throw HttpException::validation(['file' => "A aba {$table} não inclui a coluna obrigatória {$column['name']}."]);
                        }
                    }
                }

                $rows = [];
                $lastRow = $sheet->getHighestDataRow();
                for ($rowNumber = 2; $rowNumber <= $lastRow; $rowNumber++) {
                    $row = [];
                    foreach ($headers as $index => $header) {
                        if ($header === '') {
                            continue;
                        }
                        $cell = $sheet->getCell([$index + 1, $rowNumber]);
                        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                            throw HttpException::validation(['file' => "A aba {$table}, linha {$rowNumber}, contém fórmula. Exporte novamente pelo sistema."]);
                        }
                        $value = $cell->getValue();
                        if (is_string($value) && $value !== '' && stripos((string) ($tableSchema['types'][$header] ?? ''), 'BLOB') !== false
                            && str_starts_with($value, 'base64:')) {
                            $decoded = base64_decode(substr($value, 7), true);
                            if ($decoded === false) {
                                throw HttpException::validation(['file' => "A aba {$table}, linha {$rowNumber}, contém anexo inválido."]);
                            }
                            $value = $decoded;
                        }
                        if ($value !== null && !is_scalar($value)) {
                            throw HttpException::validation(['file' => "A aba {$table}, linha {$rowNumber}, contém um valor incompatível."]);
                        }
                        $row[$header] = $value === '' ? null : $value;
                    }
                    if ($row === [] || count(array_filter($row, static fn (mixed $value): bool => $value !== null && $value !== '')) === 0) {
                        continue;
                    }
                    foreach ($tableSchema['primary_key'] as $primaryKey) {
                        if (!array_key_exists($primaryKey, $row) || $row[$primaryKey] === null || $row[$primaryKey] === '') {
                            throw HttpException::validation(['file' => "A aba {$table}, linha {$rowNumber}, não contém a chave do registro."]);
                        }
                    }
                    $rows[] = $row;
                }

                if ($table === 'users') {
                    $users = $rows;
                    $excluded[] = $table;
                } elseif (in_array($table, self::EXCLUDED_TABLES, true)) {
                    $excluded[] = $table;
                } else {
                    $tables[$table] = ['rows' => $rows];
                }
            }
        } finally {
            $book->disconnectWorksheets();
        }

        if ($tables === []) {
            throw HttpException::validation(['file' => 'A planilha não contém abas de dados compatíveis para importar.']);
        }
        sort($excluded);
        return ['tables' => $tables, 'users' => $users, 'excluded_tables' => array_values(array_unique($excluded))];
    }

    /** @return array<string,array{columns:list<array>,primary_key:list<string>,types:array<string,string>,foreign_keys:list<array>}> */
    private static function schema(array $tables): array
    {
        $out = [];
        $known = array_flip(Installer::tables());
        foreach ($tables as $table) {
            if (!isset($known[$table]) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
                throw HttpException::validation(['file' => 'A planilha contém uma tabela não reconhecida.']);
            }
            $columns = Db::fetchAll("PRAGMA table_info('{$table}')");
            usort($columns, static fn (array $a, array $b): int => (int) $a['pk'] <=> (int) $b['pk']);
            $primaryKey = array_values(array_map(
                static fn (array $column): string => (string) $column['name'],
                array_filter($columns, static fn (array $column): bool => (int) $column['pk'] > 0)
            ));
            if ($columns === [] || $primaryKey === []) {
                throw HttpException::validation(['file' => "A tabela {$table} não possui uma chave de importação compatível."]);
            }
            $out[$table] = [
                'columns' => $columns,
                'primary_key' => $primaryKey,
                'types' => array_column($columns, 'type', 'name'),
                'foreign_keys' => Db::fetchAll("PRAGMA foreign_key_list('{$table}')"),
            ];
        }
        return $out;
    }

    private static function tableOrder(array $tables, array $schema): array
    {
        $remaining = array_fill_keys($tables, true);
        $ordered = [];
        while ($remaining !== []) {
            $progress = false;
            foreach (array_keys($remaining) as $table) {
                $dependencies = [];
                foreach ($schema[$table]['foreign_keys'] as $foreignKey) {
                    $reference = (string) $foreignKey['table'];
                    if (isset($remaining[$reference]) && $reference !== $table) {
                        $dependencies[] = $reference;
                    }
                }
                if ($dependencies !== []) {
                    continue;
                }
                $ordered[] = $table;
                unset($remaining[$table]);
                $progress = true;
            }
            if (!$progress) {
                $ordered = [...$ordered, ...array_keys($remaining)];
                break;
            }
        }
        return $ordered;
    }

    private static function mapUsers(array $users): array
    {
        $map = [];
        foreach ($users as $user) {
            $sourceId = (string) ($user['id'] ?? '');
            $email = trim((string) ($user['email'] ?? ''));
            if ($sourceId === '' || $email === '') {
                continue;
            }
            $localId = Db::value('SELECT id FROM users WHERE lower(email) = lower(?) LIMIT 1', [$email]);
            if ($localId !== null) {
                $map[$sourceId] = (int) $localId;
            }
        }
        return $map;
    }

    private static function mapReferences(string $table, array $sourceRow, array $schema, array $mapped, array $workbookTables): ?array
    {
        $row = $sourceRow;
        $foreignUserColumns = [];
        foreach ($schema['foreign_keys'] as $foreignKey) {
            $from = (string) $foreignKey['from'];
            $referenceTable = (string) $foreignKey['table'];
            $to = (string) $foreignKey['to'];
            $value = $row[$from] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $key = (string) $value;
            if ($referenceTable === 'users') {
                $foreignUserColumns[] = $from;
                if (!isset($mapped['users']['id'][$key])) {
                    return null;
                }
                $row[$from] = $mapped['users']['id'][$key];
                continue;
            }
            if (array_key_exists($referenceTable, $workbookTables)) {
                if (!array_key_exists($key, $mapped[$referenceTable][$to] ?? [])) {
                    return null;
                }
                $row[$from] = $mapped[$referenceTable][$to][$key];
                continue;
            }
            $exists = Db::value("SELECT 1 FROM `{$referenceTable}` WHERE `{$to}` = ? LIMIT 1", [$value]);
            if ($exists === null) {
                return null;
            }
        }
        foreach (self::USER_REFERENCE_COLUMNS as $column) {
            if (in_array($column, $foreignUserColumns, true) || !array_key_exists($column, $row)) {
                continue;
            }
            $value = $row[$column];
            if ($value === null || $value === '') {
                continue;
            }
            $sourceUserId = (string) $value;
            if (!isset($mapped['users']['id'][$sourceUserId])) {
                return null;
            }
            $row[$column] = $mapped['users']['id'][$sourceUserId];
        }
        return $row;
    }

    private static function findByPrimaryKey(string $table, array $row, array $schema): ?array
    {
        $where = [];
        $values = [];
        foreach ($schema['primary_key'] as $column) {
            if (!array_key_exists($column, $row) || $row[$column] === null) {
                return null;
            }
            $where[] = '`' . $column . '` = ?';
            $values[] = $row[$column];
        }
        return Db::fetch('SELECT * FROM `' . $table . '` WHERE ' . implode(' AND ', $where) . ' LIMIT 1', $values);
    }

    private static function sameImportedValues(array $source, array $target): bool
    {
        foreach ($source as $column => $value) {
            if (!array_key_exists($column, $target) || !self::sameValue($value, $target[$column])) {
                return false;
            }
        }
        return true;
    }

    private static function sameValue(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }
        return (string) $left === (string) $right;
    }

    private static function rememberMappings(string $table, array $sourceRow, array $targetRow, array &$mapped): void
    {
        foreach ($sourceRow as $column => $sourceValue) {
            if ($sourceValue !== null && array_key_exists($column, $targetRow)) {
                $mapped[$table][$column][(string) $sourceValue] = $targetRow[$column];
            }
        }
    }

    private static function isConstraintViolation(PDOException $error): bool
    {
        return (string) ($error->errorInfo[0] ?? $error->getCode()) === '23000'
            || in_array((int) ($error->errorInfo[1] ?? 0), [19, 787], true);
    }

    private static function isUniqueConstraintViolation(PDOException $error): bool
    {
        $driverCode = (int) ($error->errorInfo[1] ?? 0);
        $message = strtolower($error->getMessage());
        return in_array($driverCode, [1062, 1555, 2067], true)
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate entry');
    }

    private static function countSkipped(array &$table, int &$total, string $reason): void
    {
        $table['skipped']++;
        $table[$reason]++;
        $total++;
    }
}
