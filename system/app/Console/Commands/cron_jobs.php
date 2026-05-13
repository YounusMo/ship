<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Http\Controllers\treasuryController;


class cron_jobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron_jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        treasuryController::save_treasury();
    }
}
