<?php

namespace App\Models;

use App\Enums\AnswerStatus;
use Database\Factories\AnswerEditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['answer_id', 'user_id', 'old_status', 'new_status', 'old_score', 'new_score', 'reason'])]
class AnswerEdit extends Model
{
    /** @use HasFactory<AnswerEditFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_status' => AnswerStatus::class,
            'new_status' => AnswerStatus::class,
            'old_score' => 'integer',
            'new_score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Answer, $this>
     */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
