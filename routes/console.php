<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Расписание команд для WOW Рулетки
Schedule::command('wow:restore-tickets')
    ->everyThreeHours()
    ->withoutOverlapping()
    ->runInBackground();

// Персональные уведомления о доступности бесплатной прокрутки
// Запускаем каждые 5 минут для проверки индивидуальных 24-часовых таймеров
Schedule::command('wow:send-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
