<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        Schema::connection('priyasa')->create('priyasa_customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32)->unique();
            $table->string('email')->nullable()->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('last_order_at')->nullable();
            $table->decimal('lifetime_value', 14, 2)->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->timestamps();
        });

        Schema::connection('priyasa')->create('priyasa_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('priyasa_customers')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('phone', 32);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('area')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('postal_code', 16);
            $table->string('country', 2)->default('IN');
            $table->string('landmark')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['customer_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_addresses');
        Schema::connection('priyasa')->dropIfExists('priyasa_customers');
    }
};