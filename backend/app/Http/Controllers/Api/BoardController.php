<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachBoardLessonRequest;
use App\Http\Requests\BoardCommandRequest;
use App\Http\Requests\PairBoardRequest;
use App\Http\Requests\UpdateBoardOptionsRequest;
use App\Models\BoardSession;
use App\Models\Lesson;
use App\Services\BoardRemote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The classroom board (no login, identified by its secret token) and the
 * teacher phone that remote-controls it.
 *
 * @group Doska va pult
 */
class BoardController extends Controller
{
    public function __construct(private BoardRemote $remote) {}

    /**
     * A board browser tab opens a new session and gets its token and pair code.
     *
     * @unauthenticated
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => ['nullable', 'string', 'max:100']]);

        $board = $this->remote->open($request->input('name'));

        return response()->json([
            'token' => $board->token,
            'channel' => $board->broadcastChannelName(),
            'state' => $this->remote->state($board),
        ], 201);
    }

    /**
     * Current board state, used by the board after a reload or reconnect.
     *
     * @unauthenticated
     */
    public function state(string $token): JsonResponse
    {
        $board = $this->findByToken($token);
        $board->forceFill(['last_seen_at' => now()])->save();

        return response()->json(['state' => $this->remote->state($board)]);
    }

    /**
     * @unauthenticated
     */
    public function refreshPairCode(string $token): JsonResponse
    {
        $board = $this->remote->refreshPairCode($this->findByToken($token));

        return response()->json(['state' => $this->remote->state($board)]);
    }

    public function pair(PairBoardRequest $request): JsonResponse
    {
        $board = $this->remote->pair($request->string('code'), $request->user());

        return response()->json(['state' => $this->remote->state($board)]);
    }

    public function attach(AttachBoardLessonRequest $request, BoardSession $board): JsonResponse
    {
        $board = $this->remote->attach($board, Lesson::query()->findOrFail($request->integer('lesson_id')));

        return response()->json(['state' => $this->remote->state($board)]);
    }

    public function command(BoardCommandRequest $request, BoardSession $board): JsonResponse
    {
        $board = $this->remote->command($board, $request->command());

        return response()->json([
            'state' => $this->remote->state($board),
            'answer_id' => $board->answer_id,
        ]);
    }

    public function options(UpdateBoardOptionsRequest $request, BoardSession $board): JsonResponse
    {
        $board = $this->remote->updateOptions($board, $request->validated());

        return response()->json(['state' => $this->remote->state($board)]);
    }

    /**
     * The controlling phone restores its board after an app restart.
     */
    public function controllerState(BoardSession $board): JsonResponse
    {
        Gate::authorize('control', $board);

        return response()->json(['state' => $this->remote->state($board)]);
    }

    /**
     * Sent by the controlling phone every ~15 seconds while it is paired.
     */
    public function heartbeat(BoardSession $board): JsonResponse
    {
        Gate::authorize('control', $board);

        $board = $this->remote->heartbeat($board);

        return response()->json(['state' => $this->remote->state($board)]);
    }

    public function takeover(BoardSession $board): JsonResponse
    {
        Gate::authorize('control', $board);

        $board = $this->remote->allowTakeover($board);

        return response()->json(['pair_code' => $board->pair_code]);
    }

    public function release(BoardSession $board): JsonResponse
    {
        Gate::authorize('control', $board);

        $this->remote->release($board);

        return response()->json(['released' => true]);
    }

    private function findByToken(string $token): BoardSession
    {
        return BoardSession::query()->where('token', $token)->firstOrFail();
    }
}
