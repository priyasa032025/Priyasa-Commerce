<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminCommerceControlCenterService
{
    public function overview(): array
    {
        return [
            'orders' => $this->orderMetrics(),
            'payments' => $this->paymentMetrics(),
            'inventory' => $this->inventoryMetrics(),
            'fulfillment' => $this->fulfillmentMetrics(),
            'returns' => $this->returnMetrics(),
            'refunds' => $this->refundMetrics(),
            'customers' => $this->customerMetrics(),
            'support' => $this->supportMetrics(),
            'system' => ['database' => $this->databaseReady(), 'generated_at' => now()->toISOString()],
        ];
    }

    public function orders(array $filters = []): array
    {
        $table = 'priyasa_orders';
        if (!SchemaSafe50::table($table)) return ['items'=>[], 'meta'=>['total'=>0]];
        $q = DB::connection('priyasa')->table($table)->orderByDesc('id');
        foreach (['status','payment_status','payment_method'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '' && SchemaSafe50::column($table,$field)) $q->where($field,$filters[$field]);
        }
        if (!empty($filters['q'])) {
            $needle = trim((string)$filters['q']);
            $q->where(function($x) use ($needle,$table) {
                foreach (['order_number','id'] as $field) if (SchemaSafe50::column($table,$field)) $x->orWhere($field,'like','%'.$needle.'%');
            });
        }
        $per = min(100,max(1,(int)($filters['per_page'] ?? 25)));
        $p = $q->paginate($per);
        return ['items'=>$p->items(),'meta'=>['current_page'=>$p->currentPage(),'last_page'=>$p->lastPage(),'per_page'=>$p->perPage(),'total'=>$p->total()]];
    }

    public function inventory(array $filters = []): array
    {
        $items=[];
        if (SchemaSafe50::table('priyasa_inventory')) {
            $q=DB::connection('priyasa')->table('priyasa_inventory');
            if (SchemaSafe50::column('priyasa_inventory','quantity')) $q->where('quantity','<=',(int)($filters['threshold']??5));
            $items=$q->orderBy('quantity')->limit(100)->get()->map(fn($r)=>(array)$r)->all();
        }
        $warehouse=[];
        if (SchemaSafe50::table('priyasa_warehouse_inventory')) {
            $q=DB::connection('priyasa')->table('priyasa_warehouse_inventory');
            if (SchemaSafe50::column('priyasa_warehouse_inventory','quantity')) $q->where('quantity','<=',(int)($filters['threshold']??5));
            $warehouse=$q->orderBy('quantity')->limit(100)->get()->map(fn($r)=>(array)$r)->all();
        }
        return ['legacy_inventory'=>$items,'warehouse_inventory'=>$warehouse,'meta'=>['legacy_count'=>count($items),'warehouse_count'=>count($warehouse)]];
    }

    public function operations(): array
    {
        return [
            'pending_payment_orders'=>$this->countWhere('priyasa_orders','status','pending_payment'),
            'failed_payments'=>$this->countWhere('priyasa_payment_transactions','status','failed'),
            'active_reservations'=>$this->countWhereIn('priyasa_inventory_reservations','status',['reserved','active']),
            'pending_shipments'=>$this->countWhereIn('priyasa_shipments','status',['created','ready','packed','shipped','in_transit']),
            'open_support'=>$this->countWhereIn('priyasa_support_tickets','status',['open','in_progress','waiting_customer','waiting_internal']),
            'pending_notifications'=>$this->countWhereIn('priyasa_notification_outbox','status',['pending','processing','retry']),
        ];
    }

    public function audit(string $action, array $payload, ?string $actorId): void
    {
        if (!SchemaSafe50::table('priyasa_admin_audit_logs')) return;
        $columns=['action'=>$action,'actor_id'=>$actorId,'payload'=>json_encode($payload),'created_at'=>now(),'updated_at'=>now()];
        $allowed=[]; foreach ($columns as $k=>$v) if (SchemaSafe50::column('priyasa_admin_audit_logs',$k)) $allowed[$k]=$v;
        if ($allowed) DB::connection('priyasa')->table('priyasa_admin_audit_logs')->insert($allowed);
    }

    private function orderMetrics(): array { return ['total'=>$this->count('priyasa_orders'),'pending_payment'=>$this->countWhere('priyasa_orders','status','pending_payment'),'confirmed'=>$this->countWhere('priyasa_orders','status','confirmed'),'shipped'=>$this->countWhere('priyasa_orders','status','shipped'),'delivered'=>$this->countWhere('priyasa_orders','status','delivered'),'cancelled'=>$this->countWhere('priyasa_orders','status','cancelled')]; }
    private function paymentMetrics(): array { return ['transactions'=>$this->count('priyasa_payment_transactions'),'failed'=>$this->countWhere('priyasa_payment_transactions','status','failed'),'captured'=>$this->countWhereIn('priyasa_payment_transactions','status',['captured','paid'])]; }
    private function inventoryMetrics(): array { return ['inventory_rows'=>$this->count('priyasa_inventory'),'warehouse_rows'=>$this->count('priyasa_warehouse_inventory'),'active_reservations'=>$this->countWhereIn('priyasa_inventory_reservations','status',['reserved','active'])]; }
    private function fulfillmentMetrics(): array { return ['allocations'=>$this->count('priyasa_fulfillment_allocations'),'pending'=>$this->countWhereIn('priyasa_fulfillment_allocations','status',['allocated','picking','packed']),'shipments'=>$this->count('priyasa_shipments')]; }
    private function returnMetrics(): array { return ['returns'=>$this->count('priyasa_returns'),'open'=>$this->countWhereIn('priyasa_returns','status',['requested','approved','in_transit','received','qc_pending'])]; }
    private function refundMetrics(): array { return ['refunds'=>$this->count('priyasa_refunds'),'pending'=>$this->countWhereIn('priyasa_refunds','status',['pending','initiated','processing'])]; }
    private function customerMetrics(): array { return ['customers'=>$this->count('priyasa_customers'),'devices'=>$this->count('priyasa_notification_devices'),'reviews'=>$this->count('priyasa_product_reviews')]; }
    private function supportMetrics(): array { return ['tickets'=>$this->count('priyasa_support_tickets'),'open'=>$this->countWhereIn('priyasa_support_tickets','status',['open','in_progress','waiting_customer','waiting_internal'])]; }
    private function count(string $table): int { return SchemaSafe50::table($table)?(int)DB::connection('priyasa')->table($table)->count():0; }
    private function countWhere(string $table,string $column,string $value): int { return SchemaSafe50::column($table,$column)?(int)DB::connection('priyasa')->table($table)->where($column,$value)->count():0; }
    private function countWhereIn(string $table,string $column,array $values): int { return SchemaSafe50::column($table,$column)?(int)DB::connection('priyasa')->table($table)->whereIn($column,$values)->count():0; }
    private function databaseReady(): bool { try { DB::connection('priyasa')->select('select 1'); return true; } catch (\Throwable) { return false; } }
}

final class SchemaSafe50
{
    public static function table(string $table): bool { try { return DB::connection('priyasa')->getSchemaBuilder()->hasTable($table); } catch (\Throwable) { return false; } }
    public static function column(string $table,string $column): bool { try { return self::table($table) && DB::connection('priyasa')->getSchemaBuilder()->hasColumn($table,$column); } catch (\Throwable) { return false; } }
}
