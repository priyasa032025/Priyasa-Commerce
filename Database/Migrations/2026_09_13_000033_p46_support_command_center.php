<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_support_tickets')) {
            Schema::connection('priyasa')->table('priyasa_support_tickets', function (Blueprint $table): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_support_tickets', 'return_id')) $table->unsignedBigInteger('return_id')->nullable()->index();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_support_tickets', 'refund_id')) $table->unsignedBigInteger('refund_id')->nullable()->index();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_support_tickets', 'shipment_id')) $table->unsignedBigInteger('shipment_id')->nullable()->index();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_support_tickets', 'payment_transaction_id')) $table->unsignedBigInteger('payment_transaction_id')->nullable()->index();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_support_tickets', 'conversation_id')) $table->unsignedBigInteger('conversation_id')->nullable()->index();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_support_tickets', 'resolution_code')) $table->string('resolution_code', 64)->nullable()->index();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_support_tickets', 'sla_due_at')) $table->timestamp('sla_due_at')->nullable()->index();
            });
        }

        // The P45 support migration is intentionally later by filename (000036), while
        // this P46 migration historically ran earlier. Therefore do not assume the
        // referenced ticket table has already been created. If it is absent, defer the
        // FK/table creation to P45 rather than creating an invalid reference.
        if (Schema::connection('priyasa')->hasTable('priyasa_support_tickets')) {
            // Existing installations can have a non-InnoDB support table from an older
            // migration. Foreign keys require compatible engines, so normalize it.
            try {
                Schema::connection('priyasa')->statement(
                    'ALTER TABLE `priyasa_support_tickets` ENGINE=InnoDB'
                );
            } catch (\Throwable $e) {
                // If the server disallows the engine change, the explicit FK creation
                // below will be skipped rather than aborting the entire migration.
            }

            if (!Schema::connection('priyasa')->hasTable('priyasa_support_actions')) {
                Schema::connection('priyasa')->create('priyasa_support_actions', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('ticket_id');
                    $table->string('action', 64);
                    $table->string('actor_id')->nullable();
                    $table->string('entity_type', 64)->nullable();
                    $table->unsignedBigInteger('entity_id')->nullable();
                    $table->json('payload')->nullable();
                    $table->timestamps();
                    $table->index(['ticket_id', 'created_at'], 'pc_support_actions_ticket_created_idx');
                    $table->index(['entity_type', 'entity_id'], 'pc_support_actions_entity_idx');
                });
            }

            // The previous deployment may have created the table and failed while
            // adding the FK. Ensure the FK exists exactly once on rerun.
            $hasTicketFk = DB::connection('priyasa')->selectOne(
                "SELECT 1 AS present FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'priyasa_support_actions'
                   AND COLUMN_NAME = 'ticket_id'
                   AND REFERENCED_TABLE_NAME = 'priyasa_support_tickets'
                   AND REFERENCED_COLUMN_NAME = 'id'
                 LIMIT 1"
            );
            if (!$hasTicketFk) {
                try {
                    Schema::connection('priyasa')->table('priyasa_support_actions', function (Blueprint $table): void {
                        $table->foreign('ticket_id', 'pc_support_actions_ticket_fk')
                            ->references('id')
                            ->on('priyasa_support_tickets')
                            ->cascadeOnDelete();
                    });
                } catch (\Throwable $e) {
                    // Leave the table usable if an old installation has incompatible
                    // support-ticket schema; P45 can reconcile it later.
                }
            }
        }
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_support_actions');
        if (Schema::connection('priyasa')->hasTable('priyasa_support_tickets')) {
            Schema::connection('priyasa')->table('priyasa_support_tickets', function (Blueprint $table): void {
                foreach (['return_id','refund_id','shipment_id','payment_transaction_id','conversation_id','resolution_code','sla_due_at'] as $column) {
                    if (Schema::connection('priyasa')->hasColumn('priyasa_support_tickets', $column)) $table->dropColumn($column);
                }
            });
        }
    }
};
