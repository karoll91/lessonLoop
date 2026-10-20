<?php

namespace App\Services;

use App\Enums\AnswerStatus;
use App\Enums\BoardScreen;
use App\Models\Answer;
use App\Models\BoardSession;
use App\Models\Lesson;

/**
 * Builds what the classroom board is allowed to show. The board is seen by
 * students, so the correct answer and the score are included only on the
 * screens where the teacher has revealed them.
 */
class BoardPresenter
{
    public function __construct(private LessonRunner $runner) {}

    /**
     * @return array<string, mixed>
     */
    public function present(BoardSession $board): array
    {
        $board->loadMissing([
            'lesson.course.studyGroup',
            'lesson.course.subject',
            'lesson.topics',
            'answer.student',
            'answer.question.topic',
            'answer.question.options',
        ]);

        $payload = [
            'board_id' => $board->id,
            'name' => $board->name,
            'screen' => $board->screen->value,
            'is_paired' => $board->controller_id !== null,
            'controller_online' => $board->controllerOnline(),
            // Relative, so the board's own clock does not matter.
            'controller_seen_seconds_ago' => $board->controller_id !== null && $board->controller_seen_at !== null
                ? max(0, (int) $board->controller_seen_at->diffInSeconds(now()))
                : null,
            'controller_timeout' => (int) config('lessonloop.controller_timeout'),
            'options' => array_merge(BoardSession::DEFAULT_OPTIONS, $board->options ?? []),
        ];

        if ($board->pair_code !== null) {
            $payload['pair_code'] = $board->pair_code;
            $payload['pair_code_expires_at'] = $board->pair_code_expires_at?->toIso8601String();
        }

        if ($board->lesson !== null) {
            $payload['lesson'] = $this->lesson($board->lesson);
        }

        if ($board->answer !== null && in_array($board->screen, [BoardScreen::Drawing, BoardScreen::Question, BoardScreen::Answer, BoardScreen::Score], true)) {
            $payload['current'] = $this->current($board, $board->answer);
        }

        if ($board->screen === BoardScreen::Summary && $board->lesson !== null) {
            $payload['summary'] = $this->summary($board, $board->lesson);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function lesson(Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'group' => $lesson->course->studyGroup->name,
            'subject' => $lesson->course->subject->name,
            'sequence' => $lesson->sequence,
            'question_count' => $lesson->question_count,
            'asked_count' => $this->runner->askedCount($lesson),
            'present_count' => $lesson->attendances()->where('is_present', true)->count(),
            'topics' => $lesson->topics->sortBy('position')->pluck('title')->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function current(BoardSession $board, Answer $answer): array
    {
        $screen = $board->screen;
        $question = $answer->question;

        $current = [
            'student' => $board->option('show_student_name')
                ? ['full_name' => $answer->student->full_name]
                : null,
        ];

        if ($screen !== BoardScreen::Drawing) {
            $current['question'] = [
                'text' => $question->text,
                'type' => $question->type->value,
                'difficulty' => $question->difficulty->value,
                'topic' => $question->topic->title,
                'image_path' => $question->image_path,
                'options' => $question->options
                    ->map(fn ($option): array => ['label' => $option->label, 'text' => $option->text])
                    ->values()
                    ->all(),
            ];
        }

        if (in_array($screen, [BoardScreen::Answer, BoardScreen::Score], true) && $board->option('show_answer')) {
            $current['correct'] = [
                'answer_text' => $question->answer_text,
                'option_label' => $question->correctOptionLabel(),
                'chosen_option' => $answer->chosen_option,
            ];
        }

        if ($screen === BoardScreen::Score && $board->option('show_score') && $answer->status->isScored()) {
            $current['result'] = [
                'score' => $answer->score,
                'passed' => $answer->isPassing(),
                'rest_lessons' => $answer->isPassing() ? (int) config('lessonloop.cooldown_lessons') - 1 : 0,
            ];
        }

        return $current;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(BoardSession $board, Lesson $lesson): array
    {
        $scored = $lesson->answers()
            ->with('student')
            ->whereIn('status', [AnswerStatus::Graded, AnswerStatus::NoAnswer])
            ->orderByDesc('score')
            ->get();

        $passScore = (int) config('lessonloop.pass_score');

        return [
            'average' => $scored->isEmpty() ? null : (int) round($scored->avg('score')),
            'passed_count' => $scored->where('score', '>=', $passScore)->count(),
            'retry_count' => $scored->where('score', '<', $passScore)->count(),
            'results' => $board->option('show_student_name')
                ? $scored->map(fn (Answer $answer): array => [
                    'full_name' => $answer->student->full_name,
                    'score' => $board->option('show_score') ? $answer->score : null,
                    'passed' => $answer->score >= $passScore,
                ])->values()->all()
                : [],
        ];
    }
}
