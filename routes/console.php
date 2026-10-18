<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('newspub:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('newspub:check-ai-spend')->hourly();
Schedule::command('newspub:check-domain-certificates')->everyTenMinutes()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
