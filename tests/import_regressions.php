<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/resources/views/pages/import/index.php');
$service = (string) file_get_contents($root . '/app/Services/ImportService.php');
$checks = [
    'result errors is null-safe' => str_contains($view, 'result?.errors'),
    'imported created is null-safe' => str_contains($view, 'imported?.created'),
    'preview validates missing location for initial stock' => str_contains($service, 'Local obrigatório'),
    'preview exposes validation errors' => str_contains($service, "'errors' => \$msgs"),
];
$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
if ($failed !== []) {
    fwrite(STDERR, "FAIL import regressions\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
echo 'OK import regressions ' . count($checks) . " checks\n";
