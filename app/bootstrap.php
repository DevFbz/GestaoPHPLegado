<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Env;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    // Fallback autoloader (the delivery zip always ships vendor/)
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'App\\')) {
            $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
    require BASE_PATH . '/app/helpers.php';
}

Env::load(BASE_PATH . '/.env');

if (getenv('VERCEL')) {
    $host = (string) getenv('VERCEL_PROJECT_PRODUCTION_URL');
    if ($host === '') {
        $host = (string) getenv('VERCEL_URL');
    }
    $host = (string) preg_replace('#^https?://#', '', $host);
    if ($host !== '' && getenv('APP_URL') === false) {
        putenv('APP_URL=https://' . $host);
        $_ENV['APP_URL'] = 'https://' . $host;
    }
    if (getenv('DB_DRIVER') === false) {
        putenv('DB_DRIVER=sqlite');
        $_ENV['DB_DRIVER'] = 'sqlite';
    }
    if (getenv('SESSION_DRIVER') === false) {
        putenv('SESSION_DRIVER=cookie');
        $_ENV['SESSION_DRIVER'] = 'cookie';
    }
    $tmp = '/tmp/brindes';
    @mkdir($tmp, 0775, true);
    // SQLite is only an ephemeral query cache; the private JSON snapshot is durable.
    $sqliteDst = $tmp . '/json-runtime-v1.sqlite';
    if (getenv('DB_PATH') === false) {
        putenv('DB_PATH=' . $sqliteDst);
        $_ENV['DB_PATH'] = $sqliteDst;
    }
}

Config::load(BASE_PATH . '/config/config.php');
// The timezone must be active before any installer/seeder code runs. On
// Vercel the demo database is initialized during bootstrap, so setting it at
// the end would persist all first-run records in UTC.
date_default_timezone_set((string) Config::get('app.timezone', 'America/Sao_Paulo'));

if (getenv('VERCEL')) {
    Config::set('paths.storage', '/tmp/brindes/storage');
    Config::set('app.debug', false);
    $run = static function (callable $fn): void {
        try {
            $fn();
        } catch (Throwable $e) {
            error_log('[brindes] installer: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    };
    $run(static fn () => \App\Support\Installer::ensureVercelDemo());
    $restored = \App\Support\JsonDatabase::restoreFromBlob();
    if (!$restored) {
        // First request: publish the complete baseline even when installer emitted no tracked SQL writes.
        \App\Support\JsonDatabase::mirrorFromPdo();
        \App\Core\Db::clearMutationState();
    } else {
        \App\Core\Db::persistDemo();
    }
}

mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', Config::get('app.debug') && PHP_SAPI !== 'cli' ? '1' : '0');
ini_set('log_errors', '1');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$storage = (string) Config::get('paths.storage', BASE_PATH . '/storage');
foreach (['uploads/items', 'uploads/branding', 'uploads/invoices', 'signatures', 'pdf', 'backups', 'logs', 'cache'] as $dir) {
    $path = $storage . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}
