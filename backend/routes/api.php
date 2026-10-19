<?php

use App\Http\Controllers\Api\AnswerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudyGroupController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TopicController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

/*
| Classroom board: no login, the board is identified by its secret token.
*/
Route::post('/boards', [BoardController::class, 'store'])->middleware('throttle:20,1')->name('boards.store');
Route::get('/boards/{token}/state', [BoardController::class, 'state'])->name('boards.state');
Route::post('/boards/{token}/pair-code', [BoardController::class, 'refreshPairCode'])->middleware('throttle:30,1')->name('boards.pair-code');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/me/password', [AuthController::class, 'changePassword'])->middleware('throttle:10,1')->name('me.password');

    Route::apiResource('users', UserController::class);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    /*
    | Catalogue: groups, subjects, topics, students, questions, courses.
    */
    Route::apiResource('groups', StudyGroupController::class);
    Route::apiResource('subjects', SubjectController::class);
    Route::patch('/subjects/{subject}/topics/order', [TopicController::class, 'reorder'])->name('subjects.topics.reorder');
    Route::apiResource('subjects.topics', TopicController::class)->shallow();
    Route::apiResource('groups.students', StudentController::class)->shallow();
    Route::get('/subjects/{subject}/questions', [QuestionController::class, 'search'])->name('subjects.questions.search');
    Route::apiResource('topics.questions', QuestionController::class)->shallow();
    Route::apiResource('courses', CourseController::class);

    Route::post('/groups/{group}/students/import', [ImportController::class, 'students'])->name('groups.students.import');
    Route::post('/topics/{topic}/questions/import', [ImportController::class, 'questions'])->name('topics.questions.import');
    Route::get('/import-templates/{type}', [ImportController::class, 'template'])->name('imports.template');

    Route::get('/courses/{course}/journal', [JournalController::class, 'show'])->name('courses.journal');
    Route::get('/courses/{course}/journal/export', [JournalController::class, 'export'])->name('courses.journal.export');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings', [SettingsController::class, 'reset'])->name('settings.reset');

    Route::get('/lessons', [LessonController::class, 'index'])->name('lessons.index');
    Route::post('/courses/{course}/lessons', [LessonController::class, 'store'])->name('lessons.store');
    Route::get('/lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
    Route::post('/lessons/{lesson}/draw', [LessonController::class, 'draw'])->name('lessons.draw');
    Route::post('/lessons/{lesson}/finish', [LessonController::class, 'finish'])->name('lessons.finish');

    Route::post('/answers/{answer}/grade', [AnswerController::class, 'grade'])->name('answers.grade');
    Route::patch('/answers/{answer}', [AnswerController::class, 'update'])->name('answers.update');
    Route::post('/answers/{answer}/skip', [AnswerController::class, 'skip'])->name('answers.skip');
    Route::post('/answers/{answer}/redraw', [AnswerController::class, 'redraw'])->name('answers.redraw');

    /*
    | Teacher phone as the board remote.
    */
    Route::post('/boards/pair', [BoardController::class, 'pair'])->middleware('throttle:10,1')->name('boards.pair');
    Route::post('/boards/{board}/lesson', [BoardController::class, 'attach'])->name('boards.attach');
    Route::post('/boards/{board}/commands', [BoardController::class, 'command'])->name('boards.command');
    Route::patch('/boards/{board}/options', [BoardController::class, 'options'])->name('boards.options');
    Route::post('/boards/{board}/takeover-code', [BoardController::class, 'takeover'])->name('boards.takeover');
    Route::delete('/boards/{board}/controller', [BoardController::class, 'release'])->name('boards.release');
});
