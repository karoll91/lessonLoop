<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\BoardScreen;
use App\Events\BoardControllerSeen;
use App\Events\BoardControlLost;
use App\Events\BoardUpdated;
use App\Models\BoardSession;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsLessons;
use Tests\TestCase;

class BoardControllerTest extends TestCase
{
    use BuildsLessons, LazilyRefreshDatabase;

    public function test_board_opens_without_login_and_shows_a_six_digit_pair_code(): void
    {
        $response = $this->postJson(route('boards.store'), ['name' => '214-xona']);

        $response->assertCreated()->assertJsonPath('state.screen', BoardScreen::Pairing->value);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $response->json('state.pair_code'));
        $this->assertSame('board.'.$response->json('token'), $response->json('channel'));
    }

    public function test_board_state_returns_404_for_an_unknown_token(): void
    {
        $this->getJson(route('boards.state', 'not-a-token'))->assertNotFound();
    }

    public function test_teacher_mode_on_the_board_takes_the_board_by_its_token(): void
    {
        $board = BoardSession::factory()->create();
        $teacher = User::factory()->create();
        Sanctum::actingAs($teacher);

        $this->postJson(route('boards.claim'), ['token' => $board->token])
            ->assertOk()
            ->assertJsonPath('state.board_id', $board->id)
            ->assertJsonPath('state.screen', BoardScreen::Lobby->value)
            ->assertJsonPath('state.controller_online', true);

        $board->refresh();
        $this->assertSame($teacher->id, $board->controller_id);
        $this->assertNull($board->pair_code);
    }

    public function test_claiming_a_board_the_teacher_already_runs_from_the_phone_keeps_the_lesson(): void
    {
        [, $board] = $this->pairedBoardWithLesson();
        $this->command($board, 'draw');
        $lessonId = $board->fresh()->lesson_id;

        $this->postJson(route('boards.claim'), ['token' => $board->token])
            ->assertOk()
            ->assertJsonPath('state.screen', BoardScreen::Drawing->value)
            ->assertJsonPath('state.lesson.id', $lessonId);
    }

    public function test_claiming_another_teachers_board_starts_over_and_tells_that_teacher(): void
    {
        [$previous, $board] = $this->pairedBoardWithLesson();
        Sanctum::actingAs(User::factory()->create());
        Event::fake([BoardUpdated::class, BoardControlLost::class]);

        $this->postJson(route('boards.claim'), ['token' => $board->token])
            ->assertOk()
            ->assertJsonPath('state.screen', BoardScreen::Lobby->value)
            ->assertJsonMissingPath('state.lesson');

        Event::assertDispatched(BoardControlLost::class, fn (BoardControlLost $event): bool => $event->previousControllerId === $previous->id);
    }

    public function test_claim_needs_a_signed_in_teacher_and_a_real_board_token(): void
    {
        $board = BoardSession::factory()->create();

        $this->postJson(route('boards.claim'), ['token' => $board->token])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson(route('boards.claim'), ['token' => 'not-a-board'])->assertNotFound();
    }

    public function test_pairing_and_commands_work_while_reverb_is_down(): void
    {
        Broadcast::extend('down', fn (): Broadcaster => new class extends Broadcaster
        {
            public function auth($request): mixed
            {
                return null;
            }

            public function validAuthenticationResponse($request, $result): mixed
            {
                return null;
            }

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                throw new BroadcastException('Failed to connect to localhost port 8080');
            }
        });
        config(['broadcasting.connections.down' => ['driver' => 'down'], 'broadcasting.default' => 'down']);

        [, $board] = $this->pairedBoardWithLesson();
        $code = BoardSession::factory()->create()->pair_code;

        $this->postJson(route('boards.pair'), ['code' => $code])->assertOk()->assertJsonPath('state.is_paired', true);
        $this->command($board, 'draw')->assertJsonPath('state.screen', BoardScreen::Drawing->value);
        $this->postJson(route('boards.heartbeat', $board))->assertOk();
    }

    public function test_teacher_pairs_with_the_code_and_the_board_switches_to_the_lobby(): void
    {
        $board = BoardSession::factory()->create();
        $teacher = User::factory()->create();
        Sanctum::actingAs($teacher);
        Event::fake([BoardUpdated::class]);

        $this->postJson(route('boards.pair'), ['code' => $board->pair_code])
            ->assertOk()
            ->assertJsonPath('state.screen', BoardScreen::Lobby->value)
            ->assertJsonPath('state.is_paired', true);

        $this->assertSame($teacher->id, $board->fresh()->controller_id);
        Event::assertDispatched(BoardUpdated::class, fn (BoardUpdated $event): bool => $event->board->is($board)
            && $event->broadcastOn()[0]->name === 'board.'.$board->token);
    }

    public function test_teacher_mode_shows_a_code_and_the_teachers_phone_joins_without_stopping_the_lesson(): void
    {
        [, $board] = $this->pairedBoardWithLesson();
        $this->command($board, 'draw');
        $lessonId = $board->fresh()->lesson_id;

        $code = $this->postJson(route('boards.takeover', $board))->assertOk()->json('pair_code');
        $this->getJson(route('boards.state', $board->token))->assertJsonPath('state.pair_code', $code);

        $this->postJson(route('boards.pair'), ['code' => $code])
            ->assertOk()
            ->assertJsonPath('state.screen', BoardScreen::Drawing->value)
            ->assertJsonPath('state.lesson.id', $lessonId)
            ->assertJsonMissingPath('state.pair_code');
    }

    public function test_returns_422_for_an_expired_pair_code(): void
    {
        $board = BoardSession::factory()->create(['pair_code_expires_at' => now()->subSecond()]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('boards.pair'), ['code' => $board->pair_code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_pairing_a_controlled_board_notifies_the_previous_teacher(): void
    {
        $previous = User::factory()->create();
        $board = BoardSession::factory()->create(['controller_id' => $previous->id]);
        Sanctum::actingAs(User::factory()->create());
        Event::fake([BoardUpdated::class, BoardControlLost::class]);

        $this->postJson(route('boards.pair'), ['code' => $board->pair_code])->assertOk();

        Event::assertDispatched(BoardControlLost::class, fn (BoardControlLost $event): bool => $event->previousControllerId === $previous->id);
    }

    public function test_returns_403_when_a_phone_that_did_not_pair_sends_a_command(): void
    {
        $board = BoardSession::factory()->create(['controller_id' => User::factory()->create()->id]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('boards.command', $board), ['command' => 'blackout'])->assertForbidden();
    }

    public function test_board_hides_the_correct_answer_and_score_until_the_teacher_reveals_them(): void
    {
        [$teacher, $board] = $this->pairedBoardWithLesson();
        Event::fake([BoardUpdated::class]);

        $drawing = $this->command($board, 'draw');
        $this->assertNotNull($drawing->json('state.current.student.full_name'));
        $this->assertNull($drawing->json('state.current.question'));

        $question = $this->command($board, 'show_question');
        $this->assertNotNull($question->json('state.current.question.text'));
        $this->assertNull($question->json('state.current.correct'));

        $answer = $this->command($board, 'reveal_answer');
        $this->assertNotNull($answer->json('state.current.correct.answer_text'));
        $this->assertNull($answer->json('state.current.result'));

        $this->postJson(route('answers.grade', $drawing->json('answer_id')), ['score' => 45])->assertOk();

        $this->command($board, 'show_score')
            ->assertJsonPath('state.current.result.score', 45)
            ->assertJsonPath('state.current.result.passed', false);
    }

    public function test_show_score_returns_422_before_grading(): void
    {
        [, $board] = $this->pairedBoardWithLesson();
        $this->command($board, 'draw');

        $this->postJson(route('boards.command', $board), ['command' => 'show_score'])->assertUnprocessable();
    }

    public function test_hidden_student_names_are_not_sent_to_the_board(): void
    {
        [, $board] = $this->pairedBoardWithLesson();
        $this->patchJson(route('boards.options', $board), ['show_student_name' => false])->assertOk();

        $this->assertNull($this->command($board, 'draw')->json('state.current.student'));
    }

    public function test_resume_returns_to_the_screen_shown_before_blackout(): void
    {
        [, $board] = $this->pairedBoardWithLesson();
        $this->command($board, 'draw');
        $this->command($board, 'show_question');

        $this->command($board, 'blackout')->assertJsonPath('state.screen', BoardScreen::Blackout->value);
        $this->command($board, 'pause')->assertJsonPath('state.screen', BoardScreen::Pause->value);

        $this->command($board, 'resume')->assertJsonPath('state.screen', BoardScreen::Question->value);
    }

    public function test_finish_shows_the_summary_with_average_and_retry_count(): void
    {
        [, $board] = $this->pairedBoardWithLesson();
        $first = $this->command($board, 'draw')->json('answer_id');
        $this->postJson(route('answers.grade', $first), ['score' => 90])->assertOk();
        $second = $this->command($board, 'draw')->json('answer_id');
        $this->postJson(route('answers.grade', $second), ['score' => 40])->assertOk();

        $this->command($board, 'finish')
            ->assertJsonPath('state.screen', BoardScreen::Summary->value)
            ->assertJsonPath('state.summary.average', 65)
            ->assertJsonPath('state.summary.passed_count', 1)
            ->assertJsonPath('state.summary.retry_count', 1);
    }

    public function test_controller_restores_its_board_after_an_app_restart(): void
    {
        [, $board] = $this->pairedBoardWithLesson();

        $this->getJson(route('boards.controller-state', $board))
            ->assertOk()
            ->assertJsonPath('state.board_id', $board->id)
            ->assertJsonPath('state.screen', BoardScreen::Lobby->value)
            ->assertJsonPath('state.lesson.id', $board->fresh()->lesson_id);
    }

    public function test_controller_state_returns_403_for_a_phone_that_does_not_control_the_board(): void
    {
        $board = BoardSession::factory()->create(['controller_id' => User::factory()->create()->id]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('boards.controller-state', $board))->assertForbidden();
        $this->postJson(route('boards.heartbeat', $board))->assertForbidden();
    }

    public function test_pairing_counts_as_the_first_heartbeat(): void
    {
        $board = BoardSession::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        Event::fake([BoardUpdated::class]);

        $this->postJson(route('boards.pair'), ['code' => $board->pair_code])
            ->assertOk()
            ->assertJsonPath('state.controller_online', true)
            ->assertJsonPath('state.controller_seen_seconds_ago', 0)
            ->assertJsonPath('state.controller_timeout', 45);
    }

    public function test_board_shows_the_controller_offline_after_45_seconds_without_a_heartbeat(): void
    {
        $this->freezeTime();
        $teacher = User::factory()->create();
        $board = BoardSession::factory()->create([
            'controller_id' => $teacher->id,
            'screen' => BoardScreen::Lobby,
            'pair_code' => null,
            'controller_seen_at' => now()->subSeconds(46),
        ]);

        $this->getJson(route('boards.state', $board->token))
            ->assertOk()
            ->assertJsonPath('state.controller_online', false)
            ->assertJsonPath('state.controller_seen_seconds_ago', 46);
    }

    public function test_heartbeat_marks_the_controller_online_and_wakes_an_offline_board_with_the_full_state(): void
    {
        $teacher = User::factory()->create();
        $board = BoardSession::factory()->create([
            'controller_id' => $teacher->id,
            'screen' => BoardScreen::Lobby,
            'pair_code' => null,
            'controller_seen_at' => now()->subMinute(),
        ]);
        Sanctum::actingAs($teacher);
        Event::fake([BoardUpdated::class, BoardControllerSeen::class]);

        $this->postJson(route('boards.heartbeat', $board))
            ->assertOk()
            ->assertJsonPath('state.controller_online', true);

        $this->assertTrue($board->fresh()->controller_seen_at->gt(now()->subSeconds(5)));
        Event::assertDispatched(BoardUpdated::class, fn (BoardUpdated $event): bool => $event->state['controller_online'] === true);
        Event::assertNotDispatched(BoardControllerSeen::class);
    }

    public function test_heartbeat_of_an_online_controller_sends_only_the_small_event(): void
    {
        $teacher = User::factory()->create();
        $board = BoardSession::factory()->create([
            'controller_id' => $teacher->id,
            'screen' => BoardScreen::Lobby,
            'pair_code' => null,
            'controller_seen_at' => now()->subSeconds(15),
        ]);
        Sanctum::actingAs($teacher);
        Event::fake([BoardUpdated::class, BoardControllerSeen::class]);

        $this->postJson(route('boards.heartbeat', $board))->assertOk();

        Event::assertNotDispatched(BoardUpdated::class);
        Event::assertDispatched(BoardControllerSeen::class, fn (BoardControllerSeen $event): bool => $event->broadcastOn()[0]->name === 'board.'.$board->token
            && $event->broadcastAs() === 'board.controller-seen');
    }

    public function test_release_puts_the_board_back_on_the_pairing_screen_with_a_new_code(): void
    {
        [, $board] = $this->pairedBoardWithLesson();

        $this->deleteJson(route('boards.release', $board))->assertOk();

        $board->refresh();
        $this->assertNull($board->controller_id);
        $this->assertSame(BoardScreen::Pairing, $board->screen);
        $this->assertNotNull($board->pair_code);
    }

    /**
     * @return array{0: User, 1: BoardSession}
     */
    private function pairedBoardWithLesson(): array
    {
        ['teacher' => $teacher, 'course' => $course, 'topic' => $topic] = $this->courseSetup();
        Sanctum::actingAs($teacher);

        $lessonId = $this->postJson(route('lessons.store', $course), ['topic_ids' => [$topic->id], 'question_count' => 3])->json('data.id');
        $board = BoardSession::factory()->create(['controller_id' => $teacher->id, 'screen' => BoardScreen::Lobby, 'pair_code' => null]);

        $this->postJson(route('boards.attach', $board), ['lesson_id' => $lessonId])
            ->assertOk()
            ->assertJsonPath('state.lesson.id', Lesson::query()->findOrFail($lessonId)->id);

        return [$teacher, $board];
    }

    private function command(BoardSession $board, string $command): TestResponse
    {
        return $this->postJson(route('boards.command', $board), ['command' => $command])->assertOk();
    }
}
