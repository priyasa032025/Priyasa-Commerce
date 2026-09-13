<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PriyasaDbHealthCommand extends Command
{
    protected $signature = 'priyasa:db-health';
    protected $description = 'Verify the dedicated Priyasa commerce database connection and core schema';

    public function handle(): int
    {
        try {
            DB::connection('priyasa')->select('select 1');
            $this->info('connection: OK');
            $tables = ['priyasa_customers','priyasa_products','priyasa_orders'];
            foreach ($tables as $table) {
                $this->line($table.': '.(Schema::connection('priyasa')->hasTable($table) ? 'OK' : 'MISSING'));
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('connection: FAILED — '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
