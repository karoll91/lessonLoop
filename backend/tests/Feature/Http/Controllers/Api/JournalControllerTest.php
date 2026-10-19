<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Answer;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_journal_shows_scores_absences_and_averages_per_student(): void
    {
        [$course, $sora, $bobur] = $this->courseWithHistory();
        Sanctum::actingAs($course->teacher);

        $response = $this->getJson(route('courses.journal', $course))->assertOk();

        $response->assertJsonCount(2, 'data.lessons')
            ->assertJsonPath('data.rows.0.full_name', 'Bobur Rahimov')
            ->assertJsonPath('data.rows.0.cells.0.status', 'absent')
            ->assertJsonPath('data.rows.0.cells.1.status', 'not_asked')
            ->assertJsonPath('data.rows.0.average', null)
            ->assertJsonPath('data.rows.1.full_name', 'Sora Aliyeva')
            ->assertJsonPath('data.rows.1.cells.0.score', 45)
            ->assertJsonPath('data.rows.1.cells.1.status', 'no_answer')
            ->assertJsonPath('data.rows.1.average', 23)
            ->assertJsonPath('data.rows.1.asked_count', 2);
    }

    public function test_returns_403_when_another_teacher_opens_the_journal(): void
    {
        [$course] = $this->courseWithHistory();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('courses.journal', $course))->assertForbidden();
    }

    public function test_exports_the_journal_as_xlsx_named_after_the_group_and_subject(): void
    {
        [$course] = $this->courseWithHistory();
        Sanctum::actingAs($course->teacher);

        $this->get(route('courses.journal.export', $course))
            ->assertOk()
            ->assertDownload('ki-21-otx2506-jurnal.xlsx');
    }

    public function test_csv_export_writes_k_for_absence_and_the_average_last(): void
    {
        [$course] = $this->courseWithHistory();
        Sanctum::actingAs($course->teacher);

        $response = $this->get(route('courses.journal.export', [$course, 'format' => 'csv']))->assertOk();
        $lines = array_map('str_getcsv', preg_split('/\r?\n/', trim(file_get_contents($response->baseResponse->getFile()->getPathname()))));

        $this->assertSame('F.I.Sh.', $lines[0][0]);
        $this->assertSame("O'rtacha", end($lines[0]));
        $this->assertSame(['Bobur Rahimov', 'K', '', ''], $lines[1]);
        $this->assertSame(['Sora Aliyeva', '45', '0', '23'], $lines[2]);
    }

    /**
     * Two lessons: Sora scored 45 then did not answer; Bobur was absent in lesson 1.
     *
     * @return array{0: Course, 1: Student, 2: Student}
     */
    private function courseWithHistory(): array
    {
        $course = Course::factory()->create([
            'study_group_id' => StudyGroup::factory()->create(['name' => 'KI-21']),
            'subject_id' => Subject::factory()->create(['code' => 'OTX2506']),
        ]);
        $sora = Student::factory()->create(['study_group_id' => $course->study_group_id, 'full_name' => 'Sora Aliyeva']);
        $bobur = Student::factory()->create(['study_group_id' => $course->study_group_id, 'full_name' => 'Bobur Rahimov']);

        $first = Lesson::factory()->finished()->for($course)->create(['sequence' => 1]);
        $second = Lesson::factory()->finished()->for($course)->create(['sequence' => 2]);

        Answer::factory()->graded(45)->for($first)->for($sora)->create();
        Answer::factory()->noAnswer()->for($second)->for($sora)->create();
        Attendance::factory()->absent()->for($first)->for($bobur)->create();

        return [$course, $sora, $bobur];
    }
}
