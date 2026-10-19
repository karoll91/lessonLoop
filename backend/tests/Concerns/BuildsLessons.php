<?php

namespace Tests\Concerns;

use App\Models\Course;
use App\Models\Question;
use App\Models\Student;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Collection;

trait BuildsLessons
{
    /**
     * A course taught by a teacher, with students and one topic full of questions.
     *
     * @return array{teacher: User, course: Course, topic: Topic, students: Collection<int, Student>}
     */
    protected function courseSetup(int $studentCount = 3, int $questionCount = 5): array
    {
        $teacher = User::factory()->create();
        $course = Course::factory()->for($teacher, 'teacher')->create();
        $topic = Topic::factory()->create(['subject_id' => $course->subject_id]);
        Question::factory()->count($questionCount)->for($topic)->create();
        $students = Student::factory()->count($studentCount)->create(['study_group_id' => $course->study_group_id]);

        return compact('teacher', 'course', 'topic', 'students');
    }
}
