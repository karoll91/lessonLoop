<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\Difficulty;
use App\Models\Course;
use App\Models\Question;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ImportControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_students_import_from_text_skips_header_duplicates_and_short_names(): void
    {
        $group = StudyGroup::factory()->create();
        Student::factory()->for($group)->create(['full_name' => 'Akmal Karimov']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('groups.students.import', $group), [
            'text' => "F.I.Sh.\nakmal  karimov\nSora Aliyeva\nA. K.\nSora Aliyeva\nIsmoilov Bekzod",
        ])->assertCreated()
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('duplicate_count', 2)
            ->assertJsonPath('errors.0.row', 4)
            ->assertJsonPath('errors.0.message', 'Juda qisqa');

        $this->assertSame(
            ['Akmal Karimov', 'Ismoilov Bekzod', 'Sora Aliyeva'],
            $group->students()->orderBy('full_name')->pluck('full_name')->all(),
        );
    }

    public function test_dry_run_previews_without_saving(): void
    {
        $group = StudyGroup::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('groups.students.import', $group), ['text' => "Sora Aliyeva\nBobur Rahimov", 'dry_run' => true])
            ->assertOk()
            ->assertJsonPath('dry_run', true)
            ->assertJsonPath('created_count', 2);

        $this->assertSame(0, $group->students()->count());
    }

    public function test_students_import_reads_an_xlsx_file_with_phone_numbers(): void
    {
        $group = StudyGroup::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());
        $file = $this->xlsx([['F.I.Sh.', 'Telefon'], ['Dilnoza Karimova', '+998901234567'], ['Nodir Saidov', '']]);

        $this->post(route('groups.students.import', $group), ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('created_count', 2);

        $this->assertSame('+998901234567', $group->students()->where('full_name', 'Dilnoza Karimova')->value('phone'));
    }

    public function test_students_import_reads_a_semicolon_separated_csv(): void
    {
        $group = StudyGroup::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());
        $file = UploadedFile::fake()->createWithContent('talabalar.csv', "F.I.Sh.;Telefon\nKamola Nazarova;+998911112233\nMadina Yusupova;\n");

        $this->post(route('groups.students.import', $group), ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('created_count', 2);

        $this->assertSame('+998911112233', $group->students()->where('full_name', 'Kamola Nazarova')->value('phone'));
    }

    public function test_returns_403_when_a_teacher_imports_students(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs($course->teacher);

        $this->postJson(route('groups.students.import', $course->study_group_id), ['text' => 'Sora Aliyeva'])->assertForbidden();
    }

    public function test_returns_422_without_file_or_text(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('groups.students.import', StudyGroup::factory()->create()), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file', 'text']);
    }

    public function test_questions_import_creates_open_and_test_questions_and_reports_bad_rows(): void
    {
        $course = Course::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $course->subject_id]);
        Sanctum::actingAs($course->teacher);
        $file = $this->xlsx([
            ['Savol', "To'g'ri javob", 'Qiyinlik', 'A', 'B', 'C', 'D', "To'g'ri variant"],
            ['Jarayon nima?', 'Dastur nusxasi', 'oson', '', '', '', '', ''],
            ['Qaysi chaqiruv jarayon yaratadi?', '', "o'rta", 'exec()', 'fork()', '', '', 'b'],
            ['Variantsiz javob?', '', 'qiyin', 'Bir', 'Ikki', '', '', 'D'],
            ['Noma\'lum qiyinlik', '', 'juda qiyin', '', '', '', '', ''],
        ]);

        $this->post(route('topics.questions.import', $topic), ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('error_count', 2)
            ->assertJsonPath('errors.0.row', 4)
            ->assertJsonPath('errors.1.row', 5);

        $test = $topic->questions()->where('type', 'test')->firstOrFail();
        $this->assertSame('B', $test->correctOptionLabel());
        $this->assertSame(Difficulty::Easy, Question::query()->where('text', 'Jarayon nima?')->firstOrFail()->difficulty);
    }

    public function test_questions_import_from_text_creates_one_open_question_per_line(): void
    {
        $course = Course::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $course->subject_id]);
        Question::factory()->for($topic)->create(['text' => 'Deadlock nima?']);
        Sanctum::actingAs($course->teacher);

        $this->postJson(route('topics.questions.import', $topic), ['text' => "Semafor nima?\ndeadlock  nima?\nMutex nima?"])
            ->assertCreated()
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('duplicate_count', 1);
    }

    public function test_template_downloads_as_xlsx(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->get(route('imports.template', 'questions'))
            ->assertOk()
            ->assertDownload('lessonloop-questions-shablon.xlsx');
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        $export = new class($rows) implements FromArray
        {
            /**
             * @param  array<int, array<int, string>>  $rows
             */
            public function __construct(private array $rows) {}

            /**
             * @return array<int, array<int, string>>
             */
            public function array(): array
            {
                return $this->rows;
            }
        };

        return UploadedFile::fake()->createWithContent('import.xlsx', Excel::raw($export, ExcelType::XLSX));
    }
}
