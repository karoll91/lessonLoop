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
