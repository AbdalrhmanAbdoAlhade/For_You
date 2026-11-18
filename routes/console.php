<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// إتاحة العمولات اللي خلصت فترة الانتظار لمحافظ المؤثرين
Schedule::command('commissions:release')->hourly()->withoutOverlapping();
Schedule::command('orders:expire-unpaid')->everyFifteenMinutes()->withoutOverlapping();
// مسح قصص المؤثرين المنتهية (القصة بتختفي من العرض بالثانية، ده بس لمسح الصفوف والصور)
Schedule::command('stories:purge')->hourly()->withoutOverlapping();
Schedule::command('otp:cleanup')->daily()->onOneServer();


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');