<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    protected $connection = 'priyasa'; public function up(): void { Schema::connection('priyasa')->create('priyasa_analytics_daily',function(Blueprint $t){$t->id();$t->date('metric_date')->unique();$t->unsignedBigInteger('orders')->default(0);$t->unsignedBigInteger('paid_orders')->default(0);$t->decimal('revenue',18,2)->default(0);$t->decimal('aov',18,2)->default(0);$t->unsignedBigInteger('customers')->default(0);$t->unsignedBigInteger('cancelled_orders')->default(0);$t->unsignedBigInteger('returned_orders')->default(0);$t->timestamps();}); }
public function down(): void { Schema::connection('priyasa')->dropIfExists('priyasa_analytics_daily'); } };
