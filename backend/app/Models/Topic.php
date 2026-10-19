<?php

namespace App\Models;

use App\Enums\QuestionType;
use Database\Factories\TopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject_id', 'title', 'position'])]
class Topic extends Model
{
    /** @use HasFactory<TopicFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Open questions without a model answer; the teacher could not check them in class.
     *
     * @return HasMany<Question, $this>
     */
    public function questionsWithoutAnswer(): HasMany
    {
        return $this->questions()
            ->where('type', QuestionType::Open)
            ->where(fn ($query) => $query->whereNull('answer_text')->orWhere('answer_text', ''));
    }
}
