<?php

declare(strict_types=1);

namespace App\Support;

/** Identifies the blank homologation dataset used by the Vercel deployment. */
final class DemoSqliteStore
{
    public static function shouldSeedDemo(): bool
    {
        return false;
    }

    public static function datasetMarker(): string
    {
        return 'homolog-blank-v3';
    }
}
