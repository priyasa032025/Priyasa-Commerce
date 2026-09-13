<?php
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminAuditService
{
    public function record(?int $userId, string $action, array $context = []): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_audit_logs')) return;
        DB::connection('priyasa')->table('priyasa_admin_audit_logs')->insert([
            'user_id'=>$userId,'action'=>$action,'resource_type'=>$context['resource_type'] ?? null,'resource_id'=>isset($context['resource_id']) ? (string)$context['resource_id'] : null,
            'method'=>$context['method'] ?? null,'route'=>$context['route'] ?? null,'request_id'=>$context['request_id'] ?? null,'ip_address'=>$context['ip_address'] ?? null,
            'user_agent'=>$context['user_agent'] ?? null,'before'=>isset($context['before']) ? json_encode($context['before']) : null,
            'after'=>isset($context['after']) ? json_encode($context['after']) : null,'metadata'=>isset($context['metadata']) ? json_encode($context['metadata']) : null,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
}
