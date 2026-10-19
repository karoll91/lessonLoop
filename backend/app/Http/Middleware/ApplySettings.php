<?php

namespace App\Http\Middleware;

use App\Services\SettingsStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Loads the admin-edited LessonLoop rules into config before the request runs.
 */
class ApplySettings
{
    public function __construct(private SettingsStore $settings) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->settings->apply();

        return $next($request);
    }
}
