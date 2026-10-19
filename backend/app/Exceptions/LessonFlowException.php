<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A lesson cannot continue the way the teacher asked, for example because
 * nobody is left to ask or the selected topics ran out of questions.
 */
class LessonFlowException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function noStudentsLeft(): self
    {
        return new self('Bu darsda so\'raladigan talaba qolmadi.', 'no_students_left');
    }

    public static function noQuestionsLeft(): self
    {
        return new self('Tanlangan mavzularda berilmagan savol qolmadi.', 'no_questions_left');
    }

    public static function questionLimitReached(): self
    {
        return new self('Darsdagi savollar soni tugadi.', 'question_limit_reached');
    }

    public static function answerPending(): self
    {
        return new self('Avval joriy talabani baholang yoki o\'tkazib yuboring.', 'answer_pending');
    }

    public static function lessonNotActive(): self
    {
        return new self('Dars faol emas.', 'lesson_not_active');
    }

    public static function answerAlreadyClosed(): self
    {
        return new self('Bu javob allaqachon baholangan.', 'answer_closed');
    }

    public static function boardNotReady(): self
    {
        return new self('Doskaga dars biriktirilmagan.', 'board_not_ready');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], 422);
    }
}
