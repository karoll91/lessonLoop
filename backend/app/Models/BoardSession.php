<?php

namespace App\Models;

use App\Enums\BoardScreen;
use Database\Factories\BoardSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['token', 'name', 'pair_code', 'pair_code_expires_at', 'controller_id', 'lesson_id', 'answer_id', 'screen', 'options', 'last_seen_at', 'controller_seen_at'])]
#[Hidden(['token'])]
class BoardSession extends Model
{
    /** @use HasFactory<BoardSessionFactory> */
    use HasFactory;

    /**
     * Default display options for the classroom board.
     *
     * @var array{show_student_name: bool, show_score: bool, show_answer: bool, timer_seconds: int|null}
     */
    public const DEFAULT_OPTIONS = [
        'show_student_name' => true,
        'show_score' => true,
        'show_answer' => true,
        'timer_seconds' => null,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'screen' => BoardScreen::class,
            'options' => 'array',
            'pair_code_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'controller_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function controller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'controller_id');
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<Answer, $this>
     */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }

    public function option(string $key): mixed
    {
        return ($this->options ?? [])[$key] ?? self::DEFAULT_OPTIONS[$key] ?? null;
    }

    /**
     * The paired phone sent a heartbeat recently enough.
     */
    public function controllerOnline(): bool
    {
        return $this->controller_id !== null
            && $this->controller_seen_at !== null
            && $this->controller_seen_at->gt(now()->subSeconds((int) config('lessonloop.controller_timeout')));
    }

    public function broadcastChannelName(): string
    {
        return 'board.'.$this->token;
    }
}
