<?php

namespace Migrator;

use Illuminate\Console\Command;

class MigratorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrator';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate your app/schema.txt automatically';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Making sure all migrations are applied');

        $m = new Migrator();
        try {
            $m->migrate(file_get_contents(database_path('schema.txt')));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return;
        }

        foreach ($m->modelsCreated as $file => $content) {
            $this->info("Created model: " . basename($file));
        }

        foreach ($m->modelsCreated as $file => $content) {
            $this->info("Updated model: " . basename($file));
        }

        foreach ($m->migrationsCreated as $file => $content) {
            $this->info("Created migration: " . basename($file));
        }

        $this->info('Done!');
    }
}
