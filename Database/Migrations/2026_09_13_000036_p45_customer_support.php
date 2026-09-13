<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_support_tickets')) {
            Schema::connection('priyasa')->create('priyasa_support_tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('priyasa_customers')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('priyasa_orders')->nullOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained('priyasa_order_items')->nullOnDelete();
                $table->string('ticket_number')->unique();
                $table->string('category', 48);
                $table->string('priority', 16)->default('normal');
                $table->string('status', 24)->default('open');
                $table->string('subject', 180);
                $table->text('last_message')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->string('assigned_to')->nullable();
                $table->timestamp('first_response_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['customer_id', 'status', 'created_at']);
                $table->index(['order_id', 'created_at']);
                $table->index(['status', 'priority', 'last_message_at']);
            });
        }

        if (!Schema::connection('priyasa')->hasTable('priyasa_support_messages')) {
            Schema::connection('priyasa')->create('priyasa_support_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('priyasa_support_tickets')->cascadeOnDelete();
                $table->string('sender_type', 16); // customer, admin, system
                $table->string('sender_id')->nullable();
                $table->text('message');
                $table->json('attachments')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['ticket_id', 'created_at']);
            });
        }

        if (!Schema::connection('priyasa')->hasTable('priyasa_support_events')) {
            Schema::connection('priyasa')->create('priyasa_support_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('priyasa_support_tickets')->cascadeOnDelete();
                $table->string('event_type', 48);
                $table->string('actor_type', 16)->nullable();
                $table->string('actor_id')->nullable();
                $table->string('from_value')->nullable();
                $table->string('to_value')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['ticket_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_support_events');
        Schema::connection('priyasa')->dropIfExists('priyasa_support_messages');
        Schema::connection('priyasa')->dropIfExists('priyasa_support_tickets');
    }
};
