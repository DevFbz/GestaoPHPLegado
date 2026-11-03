<?php

declare(strict_types=1);

/**
 * Exporta o banco operacional para storage/json, uma tabela por arquivo.
 * Não exporta hashes, tokens ou segredos de sessão.
 *
 * Uso:
 *   php bin/export-json.php
 *   php bin/export-json.php --dir=C:/tmp/brindes-json
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Db;
use App\Support\Installer;

$options = getopt('', ['dir:']);
$dir = (string) ($options['dir'] ?? (BASE_PATH . '/storage/json'));
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, "Não foi possível criar {$dir}.\n");
    exit(1);
}

$tables = [
    'roles', 'permissions', 'role_permissions', 'departments', 'categories', 'locations', 'suppliers',
    'industries', 'users', 'items', 'stock', 'stock_positions', 'stock_movements', 'events',
    'event_allocations', 'requests', 'request_items', 'request_status_history', 'approval_rules',
    'approvals', 'deliveries', 'delivery_items', 'notifications', 'audit_log', 'settings',
];
$available = array_flip(Installer::tables());
$counts = [];
$redacted = ['password_hash', 'session_version', 'token_hash', 'reset_token', 'csrf_token', 'last_error'];

foreach ($tables as $table) {
    if (!isset($available[$table])) {
        continue;
    }
    $rows = Db::fetchAll('SELECT * FROM `' . $table . '`');
    if ($table === 'users') {
        foreach ($rows as &$row) {
            foreach ($redacted as $field) {
                unset($row[$field]);
            }
        }
        unset($row);
    }
    $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $table . '.json';
    $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    file_put_contents($path, $json . PHP_EOL, LOCK_EX);
    $counts[$table] = count($rows);
}

$meta = [
    'schema' => 1,
    'generated_at' => now(),
    'timezone' => date_default_timezone_get(),
    'source' => Config::get('db.driver', 'mysql'),
    'tables' => $counts,
    'redacted_user_fields' => $redacted,
];
file_put_contents(
    rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'meta.json',
    json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
    LOCK_EX
);

echo 'JSON exportado: ' . array_sum($counts) . " registros em " . count($counts) . " tabelas para {$dir}" . PHP_EOL;
