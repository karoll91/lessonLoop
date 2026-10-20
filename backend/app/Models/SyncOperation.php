<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One grade or skip sent by the phone, remembered by its client_uuid.
 */
#[Fillable(['user_id', 'client_uuid', 'type', 'answer_id', 'status', 'error_code', 'message', 'performed_at'])]
class SyncOperation extends Model
{
    public const APPLIED = 'applied';

    public const FAILED = 'failed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Answer, $this>
     */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }
}
