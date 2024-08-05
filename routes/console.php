<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


Artisan::command('syncForge', function () {
    $this->info('Syncing Forge');
    $forgeApi = new \App\Helpers\ForgeApi();
    $forgeApi->syncForge();
})->purpose('Sync Forge')->daily();
