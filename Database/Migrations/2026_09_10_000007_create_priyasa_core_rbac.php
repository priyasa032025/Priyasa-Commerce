<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        Schema::connection('priyasa')->create('priyasa_admin_roles', function (Blueprint $table) { $table->id(); $table->string('name')->unique(); $table->string('slug')->unique(); $table->timestamps(); });
        Schema::connection('priyasa')->create('priyasa_admin_permissions', function (Blueprint $table) { $table->id(); $table->string('name')->unique(); $table->timestamps(); });
        Schema::connection('priyasa')->create('priyasa_admin_role_permission', function (Blueprint $table) { $table->foreignId('role_id')->constrained('priyasa_admin_roles')->cascadeOnDelete(); $table->foreignId('permission_id')->constrained('priyasa_admin_permissions')->cascadeOnDelete(); $table->primary(['role_id','permission_id']); });
        Schema::connection('priyasa')->create('priyasa_admin_user_role', function (Blueprint $table) { $table->unsignedBigInteger('admin_user_id'); $table->foreignId('role_id')->constrained('priyasa_admin_roles')->cascadeOnDelete(); $table->primary(['admin_user_id','role_id']); });
    }
    public function down(): void { foreach (['priyasa_admin_user_role','priyasa_admin_role_permission','priyasa_admin_permissions','priyasa_admin_roles'] as $table) Schema::connection('priyasa')->dropIfExists($table); }
};
