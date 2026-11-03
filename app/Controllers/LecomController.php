<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\LecomService;

final class LecomController
{
    /** POST /api/lecom/process/start */
    public function start(Request $request): Response
    {
        return Response::created(LecomService::startConfiguredProcess());
    }
}
