<?php
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AutomationRuleService
{
    public function run(string $event, array $payload = []): int
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_automation_rules')) return 0;
        $rules = DB::connection('priyasa')->table('priyasa_automation_rules')->where('is_active', true)->where('event_key', $event)->limit(100)->get();
        $count = 0;
        foreach ($rules as $rule) {
            DB::connection('priyasa')->table('priyasa_automation_runs')->insertOrIgnore([
                'rule_id'=>$rule->id,'event_key'=>$event,'entity_type'=>$payload['entity_type'] ?? null,'entity_id'=>$payload['entity_id'] ?? null,
                'payload'=>json_encode($payload),'status'=>'queued','created_at'=>now(),'updated_at'=>now(),
            ]); $count++;
        }
        return $count;
    }
}
