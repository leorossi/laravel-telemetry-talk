<?php

namespace App\Http\Controllers;

use App\Exceptions\WarehouseSyncFailedException;

class FailingReportController extends Controller
{
    public function __invoke(): never
    {
        throw new WarehouseSyncFailedException(
            'Warehouse sync failed: upstream returned 503 for warehouse ams-1.'
        );
    }
}
