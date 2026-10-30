<?php

namespace App\Services;

use App\Enums\BoardCommand;
use App\Enums\BoardScreen;
use App\Events\BoardControllerSeen;
use App\Events\BoardControlLost;
use App\Events\BoardUpdated;
use App\Exceptions\LessonFlowException;
use App\Models\Answer;
use App\Models\BoardSession;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Random\Randomizer;

/**
 * Pairs a classroom board with a teacher phone and applies the phone's
 * commands to the board, broadcasting every change.
 */
class BoardRemote
{
    public function __construct(
        private LessonRunner $runner,
        private BoardPresenter $presenter,
        private Randomizer $randomizer,
    ) {}

    public function open(?string $name = null): BoardSession
    {
        $board = new BoardSession([
            'token' => Str::random(48),
            'name' => $name,
            'screen' => BoardScreen::Pairing,
        ]);
        $this->assignPairCode($board);
        $board->save();

        return $board;
    }

    public function refreshPairCode(BoardSession $board): BoardSession
    {
        if ($board->controller_id === null) {
            $this->assignPairCode($board);
            $board->save();
            $this->broadcast($board);
        }

        return $board;
    }

    /**
     * Pair the board that shows the given code with the teacher. A board that
     * another teacher controls is taken over and that teacher is notified.
     * The teacher who already runs it (teacher mode on the board) only adds
     * the phone: the lesson stays where it is.
     */
    public function pair(string $code, User $teacher): BoardSession
    {
        $board = BoardSession::query()
            ->where('pair_code', $code)
            ->where('pair_code_expires_at', '>', now())
            ->first();

        if ($board === null) {
            throw ValidationException::withMessages(['code' => 'Kod noto\'g\'ri yoki muddati tugagan.']);
        }

        if ($board->controller_id === $teacher->id) {
            return $this->takeControl($board, $teacher, []);
        }

        return $this->takeControl($board, $teacher, ['screen' => BoardScreen::Lobby]);
    }

    /**
     * A teacher signed in on the board itself (teacher mode) controls it. The
     * board proves itself with its secret token, so no code is needed. If the
     * teacher already controls it (e.g. from the phone), the lesson goes on;
     * a board that someone else controlled starts over in the lobby.
     */
    public function claim(BoardSession $board, User $teacher): BoardSession
    {
        if ($board->controller_id === $teacher->id) {
            $board->forceFill(['controller_seen_at' => now()])->save();

            return $board;
        }

        return $this->takeControl($board, $teacher, [
            'screen' => BoardScreen::Lobby,
            'lesson_id' => null,
            'answer_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function takeControl(BoardSession $board, User $teacher, array $changes): BoardSession
    {
        $previousControllerId = $board->controller_id;

        $board->update($changes + [
            'controller_id' => $teacher->id,
            'pair_code' => null,
            'pair_code_expires_at' => null,
            'controller_seen_at' => now(),
        ]);

        if ($previousControllerId !== null && $previousControllerId !== $teacher->id) {
            $this->send(new BoardControlLost($board, $previousControllerId, $teacher));
        }

        $this->broadcast($board);

        return $board;
    }

    /**
     * Show a new code on the board so another phone can take it over, without
     * disconnecting the current controller yet.
     */
    public function allowTakeover(BoardSession $board): BoardSession
    {
        $this->assignPairCode($board);
        $board->save();
        // Teacher mode shows the code on the board; it disappears once paired.
        $this->broadcast($board);

        return $board;
    }

    public function attach(BoardSession $board, Lesson $lesson): BoardSession
    {
        $board->update([
            'lesson_id' => $lesson->id,
            'answer_id' => $this->runner->pendingAnswer($lesson)?->id,
            'screen' => BoardScreen::Lobby,
        ]);

        $this->broadcast($board);

        return $board;
    }

    /**
     * Apply one command from the phone.
     *
     * @throws LessonFlowException
     */
    public function command(BoardSession $board, BoardCommand $command): BoardSession
    {
        $lesson = $board->lesson;

        if ($lesson === null && ! in_array($command, [BoardCommand::Blackout, BoardCommand::Pause, BoardCommand::Resume], true)) {
            throw LessonFlowException::boardNotReady();
        }

        $changes = match ($command) {
            BoardCommand::ShowLobby => ['screen' => BoardScreen::Lobby],
            BoardCommand::Draw => [
                'answer_id' => $this->runner->draw($lesson)->id,
                'screen' => BoardScreen::Drawing,
            ],
            BoardCommand::Redraw => [
                'answer_id' => $this->runner->redraw($this->requireAnswer($board))->id,
                'screen' => BoardScreen::Drawing,
            ],
            BoardCommand::ShowQuestion, BoardCommand::HideAnswer => $this->screenForAnswer($board, BoardScreen::Question),
            BoardCommand::RevealAnswer => $this->screenForAnswer($board, BoardScreen::Answer),
            BoardCommand::ShowScore => $this->scoreScreen($board),
            BoardCommand::Finish => [
                'lesson_id' => $this->runner->finish($lesson)->id,
                'answer_id' => null,
                'screen' => BoardScreen::Summary,
            ],
            BoardCommand::Blackout => $this->suspend($board, BoardScreen::Blackout),
            BoardCommand::Pause => $this->suspend($board, BoardScreen::Pause),
            BoardCommand::Resume => [
                'screen' => BoardScreen::tryFrom($board->option('resume_screen') ?? '') ?? BoardScreen::Lobby,
            ],
        };

        $board->fill($changes)->save();
        $board->unsetRelation('answer')->unsetRelation('lesson');

        $this->broadcast($board);

        return $board;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function updateOptions(BoardSession $board, array $options): BoardSession
    {
        $board->update(['options' => array_merge($board->options ?? [], $options)]);
        $this->broadcast($board);

        return $board;
    }

    /**
     * The phone lets go of the board; it goes back to the pairing screen.
     */
    public function release(BoardSession $board): BoardSession
    {
        $board->fill([
            'controller_id' => null,
            'controller_seen_at' => null,
            'lesson_id' => null,
            'answer_id' => null,
            'screen' => BoardScreen::Pairing,
        ]);
        $this->assignPairCode($board);
        $board->save();

        $this->broadcast($board);

        return $board;
    }

    /**
     * The paired phone is still there. A board that showed «Pult uzildi» gets
     * its full state back; otherwise a small heartbeat event is enough.
     */
    public function heartbeat(BoardSession $board): BoardSession
    {
        $wasOnline = $board->controllerOnline();

        $board->forceFill(['controller_seen_at' => now()])->save();

        if ($wasOnline) {
            $this->send(new BoardControllerSeen($board));
        } else {
            $this->broadcast($board);
        }

        return $board;
    }

    /**
     * @return array<string, mixed>
     */
    public function state(BoardSession $board): array
    {
        return $this->presenter->present($board);
    }

    private function broadcast(BoardSession $board): void
    {
        $this->send(new BoardUpdated($board, $this->presenter->present($board)));
    }

    /**
     * Push an event to the board and phone. The change is already saved, and
     * the board re-reads its state every 30 s, so Reverb being down must not
     * turn a pairing or a command into an error (the phone would then retry
     * with a used code, or think the step failed).
     */
    private function send(object $event): void
    {
        try {
            event($event);
        } catch (BroadcastException $exception) {
            Log::warning('Board update not broadcast (is Reverb running?): '.$exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function screenForAnswer(BoardSession $board, BoardScreen $screen): array
    {
        $this->requireAnswer($board);

        return ['screen' => $screen];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreScreen(BoardSession $board): array
    {
        if (! $this->requireAnswer($board)->status->isScored()) {
            throw ValidationException::withMessages(['command' => 'Avval talabani baholang.']);
        }

        return ['screen' => BoardScreen::Score];
    }

    /**
     * @return array<string, mixed>
     */
    private function suspend(BoardSession $board, BoardScreen $screen): array
    {
        $resumeScreen = in_array($board->screen, [BoardScreen::Blackout, BoardScreen::Pause], true)
            ? $board->option('resume_screen')
            : $board->screen->value;

        return [
            'screen' => $screen,
            'options' => array_merge($board->options ?? [], ['resume_screen' => $resumeScreen]),
        ];
    }

    private function requireAnswer(BoardSession $board): Answer
    {
        $answer = $board->answer;

        if ($answer === null) {
            throw ValidationException::withMessages(['command' => 'Doskada hali talaba tanlanmagan.']);
        }

        return $answer;
    }

    private function assignPairCode(BoardSession $board): void
    {
        do {
            $code = (string) $this->randomizer->getInt(100000, 999999);
        } while (BoardSession::query()->where('pair_code', $code)->exists());

        $board->pair_code = $code;
        $board->pair_code_expires_at = now()->addSeconds((int) config('lessonloop.pair_code_ttl'));
    }
}
