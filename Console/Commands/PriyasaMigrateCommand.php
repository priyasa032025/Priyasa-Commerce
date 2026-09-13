<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PriyasaMigrateCommand extends Command
{
    protected $signature = 'priyasa:migrate
                            {--status : Show the Priyasa commerce migration status instead of running migrations}
                            {--pretend : Print SQL without executing it}
                            {--force : Run in production without confirmation}';

    protected $description = 'Run PriyasaCore migrations against the dedicated commerce database';

    public function handle(): int
    {
        try {
            DB::connection('priyasa')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Priyasa commerce database connection failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('Priyasa commerce DB: '.(string) config('database.connections.priyasa.database'));

        $path = realpath(__DIR__.'/../../Database/Migrations');
        if (!$path) {
            $this->error('Priyasa migration directory not found.');
            return self::FAILURE;
        }

        if ($this->option('status')) {
            return $this->call('migrate:status', [
                '--database' => 'priyasa',
                '--path' => 'Modules/PriyasaCore/Database/Migrations',
            ]);
        }

        $args = [
            '--database' => 'priyasa',
            '--path' => 'Modules/PriyasaCore/Database/Migrations',
        ];
        if ($this->option('pretend')) $args['--pretend'] = true;
        if ($this->option('force')) $args['--force'] = true;

        return $this->call('migrate', $args);
    }
}
