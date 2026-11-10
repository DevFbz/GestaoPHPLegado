<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;

final class SyncController
{
    public function revision(Request $request): Response
    {
        $requestedSince = $request->queryInt('since');
        $since = max(0, $requestedSince ?? 0);
        $userId = Auth::id();
        $revision = (int) Db::value('SELECT COALESCE(MAX(id), 0) FROM audit_log');
        $changed = false;

        if ($requestedSince !== null && $revision > $since) {
            $changed = (bool) Db::value(
                'SELECT EXISTS(SELECT 1 FROM audit_log WHERE id > ? AND (user_id IS NULL OR user_id <> ?))',
                [$since, $userId]
            );
        }

        return Response::ok(['revision' => $revision, 'changed' => $changed]);
    }
}
