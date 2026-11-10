<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Core\Response;
use App\Support\Installer;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class DataExportService
{
    /** Authentication and transient provider fields are intentionally excluded. */
    private const REDACTED_COLUMNS = [
        'password_hash', 'session_version', 'token_hash', 'reset_token', 'csrf_token',
        'last_error', 'api_key', 'secret', 'access_token', 'refresh_token',
    ];

    private const TABLE_LABELS = [
        'roles' => 'Perfis', 'permissions' => 'Permissões', 'role_permissions' => 'Permissões dos perfis',
        'departments' => 'Departamentos', 'categories' => 'Categorias', 'locations' => 'Localizações',
        'suppliers' => 'Fornecedores', 'industries' => 'Indústrias', 'users' => 'Usuários',
        'items' => 'Brindes', 'stock' => 'Estoque', 'events' => 'Eventos',
        'event_allocations' => 'Cotas dos eventos', 'requests' => 'Solicitações',
        'request_items' => 'Itens das solicitações', 'request_status_history' => 'Histórico das solicitações',
        'approval_rules' => 'Regras de aprovação', 'approvals' => 'Aprovações', 'deliveries' => 'Entregas',
        'delivery_items' => 'Itens das entregas', 'stock_movements' => 'Movimentações do estoque',
        'stock_positions' => 'Posições do estoque', 'notifications' => 'Notificações',
        'audit_log' => 'Auditoria', 'settings' => 'Configurações', 'login_attempts' => 'Tentativas de login',
        'password_resets' => 'Redefinições de senha', 'idempotency_keys' => 'Chaves de idempotência',
        'sequences' => 'Sequências', 'stock_exit_orders' => 'Ordens de saída',
    ];

    private const COLUMN_LABELS = [
        'id' => 'Identificador', 'created_at' => 'Criado em', 'updated_at' => 'Atualizado em',
        'deleted_at' => 'Excluído em', 'name' => 'Nome', 'description' => 'Descrição',
        'title' => 'Título', 'code' => 'Código', 'status' => 'Status', 'active' => 'Ativo',
        'email' => 'E-mail', 'phone' => 'Telefone', 'document' => 'Documento', 'role_id' => 'Perfil',
        'user_id' => 'Usuário', 'item_id' => 'Brinde', 'event_id' => 'Evento', 'industry_id' => 'Indústria',
        'category_id' => 'Categoria', 'supplier_id' => 'Fornecedor', 'location_id' => 'Localização',
        'quantity' => 'Quantidade', 'qty' => 'Quantidade', 'unit_value' => 'Valor unitário',
        'total_value' => 'Valor total', 'balance' => 'Saldo', 'stock' => 'Estoque',
        'received_by_name' => 'Nome de quem recebeu', 'received_by_email' => 'E-mail de quem recebeu',
        'signature_path' => 'Assinatura', 'notes' => 'Observações', 'message' => 'Mensagem',
        'type' => 'Tipo', 'reason' => 'Motivo', 'address' => 'Endereço', 'city' => 'Cidade',
        'state' => 'Estado', 'zip_code' => 'CEP', 'country' => 'País', 'started_at' => 'Iniciado em',
        'finished_at' => 'Finalizado em', 'approved_at' => 'Aprovado em', 'expires_at' => 'Expira em',
    ];

    public static function exportAll(): Response
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);
        $blue = self::brandColor();
        $lightBlue = self::mixWithWhite($blue, 0.90);
        $company = (string) SettingsService::get('company_name', 'Controle de Brindes');
        $generatedAt = date('d/m/Y H:i');
        $summary = $book->createSheet();
        $summary->setTitle('Resumo');
        $summaryRows = [];

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
            $sheet->setTitle(self::safeSheetTitle($table, $book));
            $label = self::TABLE_LABELS[$table] ?? self::humanize($table);
            $lastColumn = max(1, count($columns));
            $lastLetter = self::columnLetter($lastColumn);
            $sheet->mergeCells("A1:{$lastLetter}1");
            $sheet->setCellValue('A1', $label);
            $sheet->mergeCells("A2:{$lastLetter}2");
            $sheet->setCellValue('A2', "Dados de {$company} · exportado em {$generatedAt}");
            $sheet->mergeCells("A3:{$lastLetter}3");
            $sheet->setCellValue('A3', 'Campos de autenticação e tokens foram omitidos por segurança.');
            $sheet->getStyle("A1:{$lastLetter}1")->applyFromArray(self::titleStyle($blue));
            $sheet->getStyle("A2:{$lastLetter}2")->applyFromArray(self::subtitleStyle($blue));
            $sheet->getStyle("A3:{$lastLetter}3")->applyFromArray(self::noteStyle());

            foreach ($columns as $columnIndex => $column) {
                $cell = $sheet->getCell([$columnIndex + 1, 4]);
                $cell->setValueExplicit(self::columnLabel($column), DataType::TYPE_STRING);
                $cell->getStyle()->applyFromArray(self::headerStyle($blue));
                $sheet->getColumnDimension(self::columnLetter($columnIndex + 1))->setWidth(self::columnWidth($column));
            }
            $sheet->getRowDimension(1)->setRowHeight(28);
            $sheet->getRowDimension(4)->setRowHeight(24);
            $rowIndex = 5;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if ($rowIndex > 1048576) {
                    throw new RuntimeException("A tabela {$table} excede o limite de linhas do Excel.");
                }
                foreach ($columns as $columnIndex => $column) {
                    self::writeValue($sheet, $columnIndex + 1, $rowIndex, $row[$column] ?? null, $column);
                }
                if ($rowIndex % 2 === 0) {
                    $sheet->getStyle("A{$rowIndex}:{$lastLetter}{$rowIndex}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF7FAFC');
                }
                $rowIndex++;
            }
            $dataEnd = max(4, $rowIndex - 1);
            $sheet->freezePane('A5');
            if ($columns !== []) {
                $sheet->setAutoFilter("A4:{$lastLetter}{$dataEnd}");
                $sheet->getStyle("A4:{$lastLetter}{$dataEnd}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB('FFE2E8F0');
            }
            $sheet->getSheetView()->setZoomScale(90);
            $summaryRows[] = [$label, $table, $rowIndex - 5, count($columns), $sheet->getTitle()];
        }

        self::buildSummary($summary, $summaryRows, $company, $generatedAt, $blue, $lightBlue);
        $book->setActiveSheetIndex(0);
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
            return Response::download($content, 'exportacao-completa-' . date('Ymd-His') . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        } finally {
            $book->disconnectWorksheets();
            @unlink($path);
        }
    }

    private static function buildSummary($sheet, array $rows, string $company, string $generatedAt, string $blue, string $lightBlue): void
    {
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'Exportação completa de dados');
        $sheet->mergeCells('A2:E2');
        $sheet->setCellValue('A2', "{$company} · arquivo informativo para consulta e análise");
        $sheet->mergeCells('A3:E3');
        $sheet->setCellValue('A3', "Gerado em {$generatedAt}. Use os filtros de cada aba para localizar informações rapidamente.");
        $sheet->getStyle('A1:E1')->applyFromArray(self::titleStyle($blue));
        $sheet->getStyle('A2:E2')->applyFromArray(self::subtitleStyle($blue));
        $sheet->getStyle('A3:E3')->applyFromArray(self::noteStyle());
        $sheet->fromArray(['Aba', 'Tabela técnica', 'Registros', 'Colunas', 'Nome da aba'], null, 'A5');
        $sheet->getStyle('A5:E5')->applyFromArray(self::headerStyle($blue));
        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A' . (6 + $index));
            if ($index % 2 === 0) {
                $sheet->getStyle('A' . (6 + $index) . ':E' . (6 + $index))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF7FAFC');
            }
        }
        $end = max(5, 5 + count($rows));
        $sheet->setAutoFilter("A5:E{$end}");
        $sheet->freezePane('A6');
        foreach (['A' => 28, 'B' => 28, 'C' => 14, 'D' => 12, 'E' => 28] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getStyle("A5:E{$end}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB('FFE2E8F0');
        $sheet->getSheetView()->setZoomScale(95);
    }

    private static function writeValue($sheet, int $column, int $row, mixed $value, string $columnName): void
    {
        $cell = $sheet->getCellByColumnAndRow($column, $row);
        if ($value === null) {
            return;
        }
        if (!is_scalar($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        $value = (string) $value;
        if (str_contains($value, "\0")) {
            $value = 'base64:' . base64_encode($value);
        }
        $lower = strtolower($columnName);
        $numeric = is_numeric($value) && !preg_match('/(^|_)(code|document|phone|zip|cep)($|_)/', $lower);
        if ($numeric) {
            $cell->setValue((float) $value == (int) $value ? (int) $value : (float) $value);
        } else {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
        }
    }

    private static function columnLabel(string $column): string
    {
        $key = strtolower($column);
        if (isset(self::COLUMN_LABELS[$key])) return self::COLUMN_LABELS[$key];
        return self::humanize($column);
    }

    private static function humanize(string $value): string
    {
        $value = preg_replace('/^is_/', '', $value) ?? $value;
        return mb_convert_case(str_replace('_', ' ', $value), MB_CASE_TITLE, 'UTF-8');
    }

    private static function safeSheetTitle(string $table, Spreadsheet $book): string
    {
        $base = mb_substr(self::TABLE_LABELS[$table] ?? self::humanize($table), 0, 31);
        $title = $base; $suffix = 2;
        while ($book->getSheetByName($title) !== null) {
            $title = mb_substr($base, 0, 28) . ' ' . $suffix++;
        }
        return $title;
    }

    private static function columnLetter(int $column): string
    {
        $letter = '';
        while ($column > 0) { $remainder = ($column - 1) % 26; $letter = chr(65 + $remainder) . $letter; $column = intdiv($column - 1, 26); }
        return $letter;
    }

    private static function columnWidth(string $column): int
    {
        $lower = strtolower($column);
        return str_contains($lower, 'description') || str_contains($lower, 'message') || str_contains($lower, 'notes') ? 38 : (str_contains($lower, 'email') ? 30 : 18);
    }

    private static function brandColor(): string
    {
        $color = strtoupper((string) SettingsService::get('primary_color', '#2563EB'));
        return preg_match('/^#[0-9A-F]{6}$/', $color) ? substr($color, 1) : '2563EB';
    }

    private static function mixWithWhite(string $hex, float $ratio): string
    {
        $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
        return sprintf('%02X%02X%02X', (int) ($r + (255 - $r) * $ratio), (int) ($g + (255 - $g) * $ratio), (int) ($b + (255 - $b) * $ratio));
    }

    private static function titleStyle(string $blue): array
    {
        return ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $blue]], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]];
    }

    private static function subtitleStyle(string $blue): array
    {
        return ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $blue]], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF2FF']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]];
    }

    private static function noteStyle(): array
    {
        return ['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']], 'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER]];
    }

    private static function headerStyle(string $blue): array
    {
        return ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $blue]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];
    }
}
