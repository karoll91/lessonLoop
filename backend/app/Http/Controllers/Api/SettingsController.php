<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Services\SettingsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Random queue rules (W11, M13). Everyone reads them; only admins change them.
 * Changes apply to lessons drawn from now on.
 *
 * @group Sozlamalar
 */
class SettingsController extends Controller
{
    public function __construct(private SettingsStore $settings) {}

    public function show(): JsonResponse
    {
        return $this->response();
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->settings->update($request->validated());

        return $this->response();
    }

    public function reset(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $this->settings->reset();

        return $this->response();
    }

    private function response(): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->values(),
            'defaults' => $this->settings->defaults(),
            'limits' => SettingsStore::RULES,
        ]);
    }
}
