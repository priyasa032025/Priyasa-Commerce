<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_roles')) {
            Schema::connection('priyasa')->create('priyasa_admin_roles', function (Blueprint $t): void {
                $t->id(); $t->string('name')->unique(); $t->string('display_name');
                $t->text('description')->nullable(); $t->boolean('is_system')->default(false); $t->boolean('is_active')->default(true); $t->timestamps();
            });
        } else {
            Schema::connection('priyasa')->table('priyasa_admin_roles', function (Blueprint $t): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_roles','display_name')) $t->string('display_name')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_roles','description')) $t->text('description')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_roles','is_system')) $t->boolean('is_system')->default(false);
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_roles','is_active')) $t->boolean('is_active')->default(true);
            });
        }

        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_permissions')) {
            Schema::connection('priyasa')->create('priyasa_admin_permissions', function (Blueprint $t): void {
                $t->id(); $t->string('name')->unique(); $t->string('display_name'); $t->string('module')->index(); $t->timestamps();
            });
        } else {
            Schema::connection('priyasa')->table('priyasa_admin_permissions', function (Blueprint $t): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_permissions','display_name')) $t->string('display_name')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_admin_permissions','module')) $t->string('module')->nullable()->index();
            });
        }

        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_role_permissions')) {
            Schema::connection('priyasa')->create('priyasa_admin_role_permissions', function (Blueprint $t): void {
                $t->foreignId('role_id')->constrained('priyasa_admin_roles')->cascadeOnDelete();
                $t->foreignId('permission_id')->constrained('priyasa_admin_permissions')->cascadeOnDelete();
                $t->primary(['role_id','permission_id']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_user_roles')) {
            Schema::connection('priyasa')->create('priyasa_admin_user_roles', function (Blueprint $t): void {
                $t->unsignedBigInteger('user_id');
                $t->foreignId('role_id')->constrained('priyasa_admin_roles')->cascadeOnDelete();
                $t->unsignedBigInteger('assigned_by')->nullable();
                $t->timestamp('assigned_at')->nullable();
                $t->primary(['user_id','role_id']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_audit_logs')) {
            Schema::connection('priyasa')->create('priyasa_admin_audit_logs', function (Blueprint $t): void {
                $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('action',120)->index();
                $t->string('resource_type',120)->nullable()->index(); $t->string('resource_id',120)->nullable()->index();
                $t->string('method',12)->nullable(); $t->string('route',255)->nullable(); $t->string('request_id',120)->nullable()->index();
                $t->string('ip_address',64)->nullable(); $t->text('user_agent')->nullable();
                $t->json('before')->nullable(); $t->json('after')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_sessions')) {
            Schema::connection('priyasa')->create('priyasa_admin_sessions', function (Blueprint $t): void {
                $t->id(); $t->unsignedBigInteger('user_id'); $t->string('token_hash',128)->unique();
                $t->string('device_name',160)->nullable(); $t->string('ip_address',64)->nullable(); $t->text('user_agent')->nullable();
                $t->timestamp('last_seen_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamp('revoked_at')->nullable(); $t->timestamps();
            });
        }

        $roles = [
            ['name'=>'super_admin','display_name'=>'Super Admin','description'=>'Full administrative access','is_system'=>true],
            ['name'=>'catalog_manager','display_name'=>'Catalog Manager','description'=>'Catalog and merchandising operations','is_system'=>true],
            ['name'=>'inventory_manager','display_name'=>'Inventory Manager','description'=>'Inventory operations','is_system'=>true],
            ['name'=>'order_manager','display_name'=>'Order Manager','description'=>'Orders and post-order operations','is_system'=>true],
            ['name'=>'customer_support','display_name'=>'Customer Support','description'=>'Customer and support operations','is_system'=>true],
            ['name'=>'marketing_manager','display_name'=>'Marketing Manager','description'=>'Promotions, CMS and discovery','is_system'=>true],
            ['name'=>'finance','display_name'=>'Finance','description'=>'Payments, refunds and finance','is_system'=>true],
            ['name'=>'fulfillment','display_name'=>'Fulfillment','description'=>'Warehouse and shipping operations','is_system'=>true],
        ];
        foreach ($roles as $role) {
            DB::connection('priyasa')->table('priyasa_admin_roles')->updateOrInsert(['name'=>$role['name']], $role + ['created_at'=>now(),'updated_at'=>now()]);
        }

        $permissions = [
            'admin.dashboard.view','catalog.view','catalog.manage','inventory.view','inventory.manage','orders.view','orders.manage',
            'fulfillment.view','fulfillment.manage','returns.view','returns.manage','refunds.view','refunds.manage','customers.view','customers.manage',
            'promotions.view','promotions.manage','cms.view','cms.manage','search.view','search.manage','notifications.view','notifications.manage',
            'finance.view','finance.manage','rbac.view','rbac.manage','audit.view','security.manage','bulk.manage','analytics.view','analytics.manage',
        ];
        foreach ($permissions as $name) {
            DB::connection('priyasa')->table('priyasa_admin_permissions')->updateOrInsert(
                ['name'=>$name],
                ['display_name'=>ucwords(str_replace(['.','_'],' ', $name)), 'module'=>explode('.',$name)[0], 'created_at'=>now(),'updated_at'=>now()]
            );
        }

        // Bridge the original singular P0/P-base RBAC tables into P18/P51's plural tables.
        if (Schema::connection('priyasa')->hasTable('priyasa_admin_user_role') && Schema::connection('priyasa')->hasTable('priyasa_admin_role_permission')) {
            $oldRoles = DB::connection('priyasa')->table('priyasa_admin_roles')->get();
            foreach ($oldRoles as $old) {
                $name = (string)($old->name ?? $old->slug ?? 'admin');
                $newId = DB::connection('priyasa')->table('priyasa_admin_roles')->where('name',$name)->value('id');
                if (!$newId) continue;
                if (isset($old->id)) {
                    $links = DB::connection('priyasa')->table('priyasa_admin_user_role')->where('role_id',$old->id)->get();
                    foreach ($links as $link) {
                        $uid = $link->admin_user_id ?? $link->user_id ?? null;
                        if ($uid) DB::connection('priyasa')->table('priyasa_admin_user_roles')->insertOrIgnore(['user_id'=>$uid,'role_id'=>$newId,'assigned_at'=>now()]);
                    }
                }
            }
            $oldPerms = DB::connection('priyasa')->table('priyasa_admin_permissions')->get();
            foreach ($oldPerms as $oldPerm) {
                $newPid = DB::connection('priyasa')->table('priyasa_admin_permissions')->where('name',$oldPerm->name)->value('id');
                if (!$newPid) continue;
                $links = DB::connection('priyasa')->table('priyasa_admin_role_permission')->where('permission_id',$oldPerm->id)->get();
                foreach ($links as $link) {
                    $oldRole = DB::connection('priyasa')->table('priyasa_admin_roles')->where('id',$link->role_id)->first();
                    if (!$oldRole) continue;
                    $roleName=(string)($oldRole->name ?? $oldRole->slug ?? '');
                    $newRid=DB::connection('priyasa')->table('priyasa_admin_roles')->where('name',$roleName)->value('id');
                    if ($newRid) DB::connection('priyasa')->table('priyasa_admin_role_permissions')->insertOrIgnore(['role_id'=>$newRid,'permission_id'=>$newPid]);
                }
            }
        }

        $super = DB::connection('priyasa')->table('priyasa_admin_roles')->where('name','super_admin')->value('id');
        if ($super) foreach (DB::connection('priyasa')->table('priyasa_admin_permissions')->pluck('id') as $pid) DB::connection('priyasa')->table('priyasa_admin_role_permissions')->insertOrIgnore(['role_id'=>$super,'permission_id'=>$pid]);
    }

    public function down(): void {}
};
