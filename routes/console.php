<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\FinancialScheduleRegistrar;
use App\Services\Rewards\RewardScheduleRegistrar;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

app(FinancialScheduleRegistrar::class)->register(Schedule::getFacadeRoot());
app(RewardScheduleRegistrar::class)->register(Schedule::getFacadeRoot());