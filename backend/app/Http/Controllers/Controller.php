<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Page size from the per_page query parameter, between 1 and 100.
     */
    protected function perPage(Request $request, int $default = 20): int
    {
        return max(1, min(100, $request->integer('per_page', $default)));
    }
}
