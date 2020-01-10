<?php

namespace Exceedone\Exment\Console;

use Illuminate\Console\Command;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Database\Seeder;

class InitTestCommand extends Command
{
    use CommandTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exment:inittest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize environment for test.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->initExmentCommand();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!$this->confirm('Really initialize environment? All reset this environment.')) {
            return;
        }

        \Artisan::call('migrate:reset');

        System::clearCache();

        \Artisan::call('exment:install');

        System::clearCache();
        
        $this->call('db:seed', ['--class' => Seeder\TestDataSeeder::class]);
        $this->call('db:seed', ['--class' => Seeder\WorkflowTestDataSeeder::class]);
    }
}
