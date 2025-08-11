<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;
use App\Console\Commands\AutoDeclareAuctionWinners;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// protected function schedule(Schedule $schedule)
// {
//     $schedule->command('auctions:auto-declare-winners')->everyMinute();
// }
Artisan::command('auctions:auto-declare-winners', function () {
    $this->call(AutoDeclareAuctionWinners::class);
})->describe('Automatically declare winners for ended auctions')->everyMinute();
