<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Core\Response;
use App\Support\Installer;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class DataExportService
{
    /** Authentication and transient provider fields are intentionally excluded. */
    private const REDACTED_COLUMNS = [
        'password_hash', 'session_version', 'token_hash', 'reset_token', 'csrf_token',
        'last_error', 'api_key', 'secret', 'access_token', 'refresh_token',
    ];

    public static function exportAll(): Response
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);

        foreach (Installer::tables() as $table) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
                continue;
            }

            $stmt = Db::query('SELECT * FROM `' . $table . '`');
            $columns = [];
            for ($index = 0; $index < $stmt->columnCount(); $index++) {
                $column = $stmt->getColumnMeta($index)['name'] ?? '';
                if ($column !== '' && !in_array(strtolower($column), self::REDACTED_COLUMNS, true)) {
                    $columns[] = $column;
                }
            }

            $sheet = $book->createSheet();
            $sheet->setTitle(mb_substr($table, 0, 31));
            foreach ($columns as $columnIndex => $column) {
                $sheet->setCellValueExplicit([$columnIndex + 1, 1], $column, DataType::TYPE_STRING);
            }

            $rowIndex = 2;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if ($rowIndex > 1048576) {
                    throw new RuntimeException("A tabela {$table} excede o limite de linhas do Excel.");
                }
                foreach ($columns as $columnIndex => $column) {
                    $value = $row[$column] ?? null;
                    if ($value === null) {
                        continue;
                    }
                    if (!is_scalar($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    }
                    $value = (string) $value;
                    if (str_contains($value, "\0")) {
                        $value = 'base64:' . base64_encode($value);
                    }
                    $sheet->setCellValueExplicit([$columnIndex + 1, $rowIndex], $value, DataType::TYPE_STRING);
                }
                $rowIndex++;
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'brindes-export-');
        if ($path === false) {
            throw new RuntimeException('Não foi possível preparar a exportação.');
        }

        try {
            (new Xlsx($book))->save($path);
            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException('Não foi possível ler a planilha exportada.');
            }
            return Response::download(
                $content,
                'exportacao-completa-' . date('Ymd-His') . '.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
        } finally {
            $book->disconnectWorksheets();
            @unlink($path);
        }
    }
}
