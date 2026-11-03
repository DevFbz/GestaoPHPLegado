<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Support\JsonDatabase;

$command = strtolower((string) ($argv[1] ?? ''));
if (!in_array($command, ['export', 'reset'], true)) {
    fwrite(STDERR, "Uso: php bin/json-db.php export|reset\n");
    exit(2);
}

if ($command === 'export') {
    JsonDatabase::mirrorFromPdo();
    fwrite(STDOUT, "Espelho JSON exportado para storage/json.\n");
} else {
    JsonDatabase::reset();
    fwrite(STDOUT, "Espelho JSON resetado. O banco relacional não foi alterado.\n");
}
