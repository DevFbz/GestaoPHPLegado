<?php

declare(strict_types=1);

namespace App\Support;

/** Identifies the fictional seed dataset used by Vercel demo deployments. */
final class DemoSqliteStore
{
    public static function shouldSeedDemo(): bool
    {
        return true;
    }

    public static function datasetMarker(): string
    {
        return 'demo-v7';
    }
}
