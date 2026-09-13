<?php
namespace Modules\PriyasaCore\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AnalyticsService
{
    public function overview(Carbon $from, Carbon $to): array
    {
        $orders = $this->orders($from,$to);
        $paid = $this->paidOrders($from,$to);
        $revenue = (float)($paid->sum('grand_total') ?? 0);
        $orderCount = $orders->count();
        $paidCount = $paid->count();
        return [
            'period'=>['from'=>$from->toDateString(),'to'=>$to->toDateString()],
            'orders'=>$orderCount,
            'paid_orders'=>$paidCount,
            'revenue'=>round($revenue,2),
            'aov'=>$paidCount ? round($revenue/$paidCount,2) : 0,
            'customers'=>$this->distinctCustomerCount($orders),
            'cancelled_orders'=>$this->statusCount($orders,'cancelled'),
            'returned_orders'=>$this->statusCount($orders,'returned'),
            'generated_at'=>now()->toISOString(),
        ];
    }

    public function daily(Carbon $from, Carbon $to): array
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_orders')) return [];
        $date = Schema::connection('priyasa')->hasColumn('priyasa_orders','created_at') ? 'created_at' : null;
        if (!$date) return [];
        $rows = DB::connection('priyasa')->table('priyasa_orders')->selectRaw('DATE(created_at) as day, COUNT(*) as orders')
            ->whereBetween('created_at',[$from,$to])->groupByRaw('DATE(created_at)')->orderBy('day')->get();
        $result=[];
        foreach($rows as $r) $result[$r->day]=['orders'=>(int)$r->orders,'revenue'=>0,'paid_orders'=>0];
        if (Schema::connection('priyasa')->hasColumn('priyasa_orders','grand_total')) {
            $paid = $this->paidOrderQuery($from,$to)->selectRaw('DATE(created_at) as day, COUNT(*) as paid_orders, COALESCE(SUM(grand_total),0) as revenue')->groupByRaw('DATE(created_at)')->get();
            foreach($paid as $r){ $result[$r->day]??=['orders'=>0,'revenue'=>0,'paid_orders'=>0]; $result[$r->day]['paid_orders']=(int)$r->paid_orders; $result[$r->day]['revenue']=(float)$r->revenue; }
        }
        return array_map(fn($day,$v)=>['day'=>$day]+$v,array_keys($result),array_values($result));
    }

    public function products(Carbon $from, Carbon $to, int $limit=50)
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_order_items') || !Schema::connection('priyasa')->hasTable('priyasa_product_variants')) return [];
        $q=DB::connection('priyasa')->table('priyasa_order_items as oi')
            ->join('priyasa_orders as o','o.id','=','oi.order_id')
            ->leftJoin('priyasa_product_variants as v','v.id','=','oi.variant_id')
            ->leftJoin('priyasa_products as p','p.id','=','v.product_id')
            ->whereBetween('o.created_at',[$from,$to]);
        $qty=Schema::connection('priyasa')->hasColumn('priyasa_order_items','quantity')?'SUM(oi.quantity)':'COUNT(*)';
        $sales=Schema::connection('priyasa')->hasColumn('priyasa_order_items','line_total')?'COALESCE(SUM(oi.line_total),0)':'0';
        return $q->selectRaw("COALESCE(v.product_id, p.id) as product_id, COALESCE(p.name, MAX(oi.product_name)) as product_name, {$qty} as units, {$sales} as sales")
            ->groupByRaw('COALESCE(v.product_id, p.id), p.name')->orderByDesc('sales')->limit($limit)->get();
    }

    public function persistDaily(Carbon $day): void
    {
        $from=$day->copy()->startOfDay(); $to=$day->copy()->endOfDay();
        $o=$this->overview($from,$to);
        DB::connection('priyasa')->table('priyasa_analytics_daily')->updateOrInsert(['metric_date'=>$day->toDateString()], [
            'orders'=>$o['orders'],'paid_orders'=>$o['paid_orders'],'revenue'=>$o['revenue'],'aov'=>$o['aov'],'customers'=>$o['customers'],'cancelled_orders'=>$o['cancelled_orders'],'returned_orders'=>$o['returned_orders'],'updated_at'=>now(),'created_at'=>now()
        ]);
    }

    private function orders(Carbon $from, Carbon $to) { return DB::connection('priyasa')->table('priyasa_orders')->whereBetween('created_at',[$from,$to])->get(); }
    private function paidOrders(Carbon $from, Carbon $to) { return $this->paidOrderQuery($from,$to)->get(); }
    private function paidOrderQuery(Carbon $from, Carbon $to) {
        $q=DB::connection('priyasa')->table('priyasa_orders')->whereBetween('created_at',[$from,$to]);
        if(Schema::connection('priyasa')->hasColumn('priyasa_orders','payment_status')) $q->whereIn('payment_status',['paid','captured','completed']);
        elseif(Schema::connection('priyasa')->hasColumn('priyasa_orders','status')) $q->whereNotIn('status',['pending','cancelled']);
        return $q;
    }
    private function distinctCustomerCount($orders): int { return $orders->pluck('customer_id')->filter()->unique()->count(); }
    private function statusCount($orders,string $status): int { return $orders->where('status',$status)->count(); }
}
