<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncRequest;
use App\Http\Resources\AnswerResource;
use App\Services\OfflineSync;
use Illuminate\Http\JsonResponse;

/**
 * POST /sync — the phone sends the grades and skips it queued while offline.
 *
 * @group Darslar
 */
class SyncController extends Controller
{
    public function __invoke(SyncRequest $request, OfflineSync $sync): JsonResponse
    {
        $results = $sync->replay($request->user(), $request->validated('operations'));

        return response()->json([
            'results' => array_map(function (array $result) use ($request): array {
                $operation = $result['operation'];
                $answer = $operation->answer?->load(['student', 'question.options']);

                return [
                    'client_uuid' => $operation->client_uuid,
                    'status' => $operation->status,
                    'duplicate' => $result['duplicate'],
                    'code' => $operation->error_code,
                    'message' => $operation->message,
                    'answer' => $answer === null ? null : AnswerResource::make($answer)->toArray($request),
                ];
            }, $results),
        ]);
    }
}
