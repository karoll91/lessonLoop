<?php

namespace Database\Seeders;

use App\Enums\Difficulty;
use App\Models\Course;
use App\Models\Question;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a local demo: one admin who teaches one group one subject.
     * Login: admin, password: password (local development only).
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Administrator',
            'login' => 'admin',
            'email' => null,
        ]);

        $group = StudyGroup::factory()->create(['name' => 'KI-21', 'course_year' => 3]);
        $subject = Subject::factory()->create(['name' => 'Operatsion tizimlar', 'code' => 'OTX2506']);
        Course::factory()->create([
            'study_group_id' => $group->id,
            'subject_id' => $subject->id,
            'teacher_id' => $admin->id,
        ]);

        $topics = [
            'Jarayonlar' => [
                ['Jarayon (process) va oqim (thread) o\'rtasidagi asosiy farq nimada?', 'Jarayon o\'z xotira maydoniga ega mustaqil dastur nusxasi. Oqim jarayon ichida ishlaydi va xotirani boshqa oqimlar bilan bo\'lishadi.'],
                ['Kontekst almashinuvi nima va u qachon sodir bo\'ladi?', 'Protsessor bir jarayondan boshqasiga o\'tishda joriy holatni (registrlar, PCB) saqlab, yangisini tiklaydi.'],
                ['Deadlock yuzaga kelishi uchun qaysi 4 shart kerak?', 'O\'zaro istisno, ushlab turib kutish, tortib olinmaslik, aylana kutish.'],
            ],
            'Oqimlar' => [
                ['Foydalanuvchi va yadro oqimlari farqi nimada?', 'Foydalanuvchi oqimlarini kutubxona boshqaradi, yadro ularni ko\'rmaydi; yadro oqimlarini OT rejalashtiradi.'],
                ['Mutex va semafor farqi nimada?', 'Mutex bitta egaga ega qulf; semafor hisoblagich bo\'lib, N ta oqimga ruxsat beradi.'],
            ],
        ];

        $position = 1;
        foreach ($topics as $title => $questions) {
            $topic = $subject->topics()->create(['title' => $title, 'position' => $position++]);

            foreach ($questions as [$text, $answer]) {
                Question::factory()->for($topic)->create([
                    'text' => $text,
                    'answer_text' => $answer,
                    'difficulty' => Difficulty::Medium,
                ]);
            }
        }

        Question::factory()->test('C')->for($subject->topics()->where('title', 'Jarayonlar')->firstOrFail())->create([
            'text' => 'Qaysi tizim chaqiruvi yangi jarayon yaratadi?',
            'difficulty' => Difficulty::Easy,
        ]);

        collect(['Akmal Karimov', 'Sora Aliyeva', 'Bobur Rahimov', 'Dilnoza Karimova', 'Jasur Tursunov', 'Kamola Nazarova', 'Nodir Saidov', 'Madina Yusupova'])
            ->each(fn (string $name) => Student::factory()->for($group)->create(['full_name' => $name]));
    }
}
