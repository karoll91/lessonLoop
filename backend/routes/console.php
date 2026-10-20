<?php

use App\Models\BoardSession;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Closed board tabs (BoardSession::prunable).
Schedule::command('model:prune', ['--model' => [BoardSession::class]])->daily();

// Daily database backup; hourly attempts so a PC that was off at night still gets one.
Schedule::command('lessonloop:backup', ['--if-missing'])->hourly()->withoutOverlapping();
