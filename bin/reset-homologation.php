<?php

declare(strict_types=1);

/**
 * Reset seguro de homologação.
 *
 *   php bin/reset-homologation.php
 *   php bin/reset-homologation.php --demo
 *
 * Nunca execute contra produção sem --force explícito.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Support\DemoSeeder;
use App\Support\Installer;

$options = getopt('', ['demo', 'force']);
if (Config::get('app.env') === 'production' && !isset($options['force'])) {
    fwrite(STDERR, "Recusado: APP_ENV=production. Use um ambiente de homologação ou --force consciente.\n");
    exit(1);
}

$out = static fn (string $line) => fwrite(STDOUT, $line . PHP_EOL);
Installer::install(true, $out);
if (isset($options['demo'])) {
    DemoSeeder::run($out);
}
$out(isset($options['demo']) ? 'Homologação resetada com dados demo.' : 'Homologação resetada sem dados demo.');
