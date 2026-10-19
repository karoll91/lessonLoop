<?php

namespace App\Models;

use App\Enums\AnswerStatus;
use Carbon\CarbonInterface;
use Database\Factories\AnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lesson_id', 'student_id', 'question_id', 'status', 'score', 'chosen_option', 'comment', 'pick_weight', 'pick_reason', 'graded_at'])]
class Answer extends Model
{
    /** @use HasFactory<AnswerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AnswerStatus::class,
            'score' => 'integer',
            'pick_weight' => 'integer',
            'graded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return HasMany<AnswerEdit, $this>
     */
    public function edits(): HasMany
    {
        return $this->hasMany(AnswerEdit::class)->latest('id');
    }

    /**
     * Until when the teacher may still change the grade; admins are not limited.
     */
    public function editableUntil(): ?CarbonInterface
    {
        return $this->status->isScored() ? $this->graded_at?->copy()->addHours((int) config('lessonloop.grade_edit_hours')) : null;
    }

    public function isPassing(): bool
    {
        return $this->status->isScored() && $this->score >= config('lessonloop.pass_score');
    }
}
