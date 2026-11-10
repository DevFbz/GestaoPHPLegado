<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/resources/views/pages/stock/queue.php');
$service = (string) file_get_contents($root . '/app/Services/ExitOrderService.php');
$schema = (string) file_get_contents($root . '/database/schema.sql');
$checks = [
    'operation form collects collector name' => str_contains($view, 'collector_name'),
    'operation form collects collector document' => str_contains($view, 'collector_document'),
    'operation form captures signature' => str_contains($view, 'signature'),
    'confirmation sends withdrawal evidence' => str_contains($view, 'collector_name: this.form.collector_name'),
    'backend validates collector name' => str_contains($service, "'collector_name' => 'required|string|max:150'"),
    'backend validates collector document' => str_contains($service, "'collector_document' => 'required|string|max:30'"),
    'backend persists signature' => str_contains($service, 'ImageService::persistSignature'),
    'signature payload has size limit' => str_contains((string) file_get_contents($root . '/app/Services/ImageService.php'), '2000000'),
    'confirmation stores signature transaction-safely' => str_contains($service, 'persistSignature((string) $data[\'signature\'], false)'),
    'signature endpoint requires confirmer permission' => str_contains((string) file_get_contents($root . '/app/routes.php'), "exitOrderSignature'], ['perm' => 'stock.exit_confirm'") ,
    'confirmation audits collector' => str_contains($service, "'signature_archived' => true"),
    'schema stores collector evidence' => str_contains($schema, 'collector_name'),
];
$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
if ($failed !== []) {
    fwrite(STDERR, "FAIL withdrawal regressions\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
echo 'OK withdrawal regressions ' . count($checks) . " checks\n";
