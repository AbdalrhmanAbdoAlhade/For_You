<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Super Admin bootstrap account
    |--------------------------------------------------------------------------
    |
    | تُقرأ القيم من هنا (وليس env مباشرة) حتى تظل متاحة بعد
    | تشغيل `php artisan config:cache` في بيئة الإنتاج.
    |
    */
    'email'    => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
    'name'     => env('ADMIN_NAME', 'Super Admin'),
    'phone'    => env('ADMIN_PHONE', '01000000000'),
];
