<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_roles')) {
            Schema::connection('priyasa')->create('priyasa_admin_roles', function (Blueprint $t) {
                $t->id(); $t->string('name')->unique(); $t->string('display_name');
                $t->text('description')->nullable(); $t->boolean('is_system')->default(false); $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_permissions')) {
            Schema::connection('priyasa')->create('priyasa_admin_permissions', function (Blueprint $t) {
                $t->id(); $t->string('name')->unique(); $t->string('display_name'); $t->string('module')->index(); $t->timestamps();
            });
        } else {
            Schema::connection('priyasa')->table('priyasa_admin_permissions', function (Blueprint $t) {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_permissions', 'display_name')) $t->string('display_name')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_permissions', 'module')) $t->string('module')->nullable();
            });
        }

        // The original base RBAC migration may already have created these tables
        // with the smaller legacy schema (name/slug only). Normalize them before
        // seeding P18 roles so this migration is safe on an existing commerce DB.
        if (Schema::connection('priyasa')->hasTable('priyasa_admin_roles')) {
            Schema::connection('priyasa')->table('priyasa_admin_roles', function (Blueprint $t) {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_roles', 'display_name')) $t->string('display_name')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_roles', 'slug')) $t->string('slug')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_roles', 'description')) $t->text('description')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_roles', 'is_system')) $t->boolean('is_system')->default(false);
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_roles', 'is_active')) $t->boolean('is_active')->default(true);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_role_permissions')) {
            Schema::connection('priyasa')->create('priyasa_admin_role_permissions', function (Blueprint $t) {
                $t->foreignId('role_id')->constrained('priyasa_admin_roles')->cascadeOnDelete();
                $t->foreignId('permission_id')->constrained('priyasa_admin_permissions')->cascadeOnDelete();
                $t->primary(['role_id','permission_id']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_user_roles')) {
            Schema::connection('priyasa')->create('priyasa_admin_user_roles', function (Blueprint $t) {
                $t->unsignedBigInteger('user_id');
                $t->foreignId('role_id')->constrained('priyasa_admin_roles')->cascadeOnDelete();
                $t->unsignedBigInteger('assigned_by')->nullable();
                $t->timestamp('assigned_at')->nullable();
                $t->primary(['user_id','role_id']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_audit_logs')) {
            Schema::connection('priyasa')->create('priyasa_admin_audit_logs', function (Blueprint $t) {
                $t->id(); $t->unsignedBigInteger('user_id')->nullable();
                $t->string('action',120)->index(); $t->string('resource_type',120)->nullable()->index(); $t->string('resource_id',120)->nullable()->index();
                $t->string('method',12)->nullable(); $t->string('route',255)->nullable(); $t->string('request_id',120)->nullable()->index();
                $t->string('ip_address',64)->nullable(); $t->text('user_agent')->nullable();
                $t->json('before')->nullable(); $t->json('after')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
                $t->index(['created_at','action']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_sessions')) {
            Schema::connection('priyasa')->create('priyasa_admin_sessions', function (Blueprint $t) {
                $t->id(); $t->unsignedBigInteger('user_id'); $t->string('token_hash',128)->unique();
                $t->string('device_name',160)->nullable(); $t->string('ip_address',64)->nullable(); $t->text('user_agent')->nullable();
                $t->timestamp('last_seen_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamp('revoked_at')->nullable(); $t->timestamps();
                $t->index(['user_id','revoked_at']);
            });
        }

        $roles = [
            ['name'=>'super_admin','display_name'=>'Super Admin','description'=>'Full administrative access','is_system'=>true],
            ['name'=>'catalog_manager','display_name'=>'Catalog Manager','description'=>'Catalog, product and merchandising operations','is_system'=>true],
            ['name'=>'inventory_manager','display_name'=>'Inventory Manager','description'=>'Inventory operations','is_system'=>true],
            ['name'=>'order_manager','display_name'=>'Order Manager','description'=>'Orders, fulfillment, returns and refunds','is_system'=>true],
            ['name'=>'customer_support','display_name'=>'Customer Support','description'=>'Customer and post-order support','is_system'=>true],
            ['name'=>'marketing_manager','display_name'=>'Marketing Manager','description'=>'Promotions, CMS, search and notifications','is_system'=>true],
            ['name'=>'finance','display_name'=>'Finance','description'=>'Payments, refunds and financial operations','is_system'=>true],
            ['name'=>'fulfillment','display_name'=>'Fulfillment','description'=>'Shipment and delivery operations','is_system'=>true],
        ];
        foreach ($roles as $role) {
            $payload = $role + ['slug'=>$role['name'],'created_at'=>now(),'updated_at'=>now()];
            DB::connection('priyasa')->table('priyasa_admin_roles')->updateOrInsert(['name'=>$role['name']], $payload);
        }

        $permissions = [
            'admin.dashboard.view','catalog.view','catalog.manage','inventory.view','inventory.manage','orders.view','orders.manage',
            'fulfillment.view','fulfillment.manage','returns.view','returns.manage','refunds.view','refunds.manage','customers.view','customers.manage',
            'promotions.view','promotions.manage','cms.view','cms.manage','search.view','search.manage','notifications.view','notifications.manage',
            'finance.view','finance.manage','rbac.view','rbac.manage','audit.view','security.manage','bulk.manage',
        ];
        foreach ($permissions as $name) DB::connection('priyasa')->table('priyasa_admin_permissions')->updateOrInsert(['name'=>$name], ['display_name'=>ucwords(str_replace(['.','_'],' ', $name)), 'module'=>explode('.',$name)[0], 'created_at'=>now(),'updated_at'=>now()]);
        $super = DB::connection('priyasa')->table('priyasa_admin_roles')->where('name','super_admin')->value('id');
        if ($super) {
            $ids = DB::connection('priyasa')->table('priyasa_admin_permissions')->pluck('id');
            foreach ($ids as $pid) DB::connection('priyasa')->table('priyasa_admin_role_permissions')->insertOrIgnore(['role_id'=>$super,'permission_id'=>$pid]);
        }
    }
    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_admin_sessions'); Schema::connection('priyasa')->dropIfExists('priyasa_admin_audit_logs');
        Schema::connection('priyasa')->dropIfExists('priyasa_admin_user_roles'); Schema::connection('priyasa')->dropIfExists('priyasa_admin_role_permissions');
        Schema::connection('priyasa')->dropIfExists('priyasa_admin_permissions'); Schema::connection('priyasa')->dropIfExists('priyasa_admin_roles');
    }
};
