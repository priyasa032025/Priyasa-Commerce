<?php
namespace Modules\PriyasaCore\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FinancialReportService
{
    private function range(Carbon $from, Carbon $to): array
    {
        abort_if($to->lt($from), 422, 'Invalid date range');
        abort_if($from->diffInDays($to) > 366, 422, 'Maximum range is 366 days');
        return [$from->startOfDay(), $to->endOfDay()];
    }

    public function tax(Carbon $from, Carbon $to, int $limit = 5000)
    {
        [$from, $to] = $this->range($from, $to);
        $table = 'priyasa_order_items';
        if (!Schema::connection('priyasa')->hasTable($table)) return collect();
        $cols = Schema::connection('priyasa')->getColumnListing($table);
        $select = ['id'];
        foreach (['order_id','variant_id','quantity','unit_price','tax_amount','line_total','created_at'] as $c) if (in_array($c,$cols,true)) $select[]=$c;
        return DB::connection('priyasa')->table($table)->whereBetween('created_at',[$from,$to])->orderBy('id')->limit(min($limit,10000))->get($select);
    }

    public function invoices(Carbon $from, Carbon $to, int $limit = 5000)
    {
        [$from, $to] = $this->range($from, $to);
        if (!Schema::connection('priyasa')->hasTable('priyasa_invoices')) return collect();
        $cols = Schema::connection('priyasa')->getColumnListing('priyasa_invoices');
        $preferred=['id','order_id','invoice_number','status','subtotal','discount_total','tax_total','shipping_total','grand_total','issued_at','created_at'];
        $select=[]; foreach($preferred as $c) if(in_array($c,$cols,true)) $select[]=$c;
        return DB::connection('priyasa')->table('priyasa_invoices')->whereBetween('created_at',[$from,$to])->orderBy('id')->limit(min($limit,10000))->get($select ?: ['id']);
    }

    public function refunds(Carbon $from, Carbon $to, int $limit = 5000)
    {
        [$from,$to]=$this->range($from,$to);
        if(!Schema::connection('priyasa')->hasTable('priyasa_refunds')) return collect();
        $cols=Schema::connection('priyasa')->getColumnListing('priyasa_refunds'); $preferred=['id','order_id','return_id','payment_id','provider_ref','status','amount','created_at','processed_at','refunded_at'];
        $select=[]; foreach($preferred as $c) if(in_array($c,$cols,true)) $select[]=$c;
        return DB::connection('priyasa')->table('priyasa_refunds')->whereBetween('created_at',[$from,$to])->orderBy('id')->limit(min($limit,10000))->get($select ?: ['id']);
    }

    public function wallet(Carbon $from, Carbon $to, int $limit = 5000)
    {
        [$from,$to]=$this->range($from,$to);
        if(!Schema::connection('priyasa')->hasTable('priyasa_wallet_transactions')) return collect();
        return DB::connection('priyasa')->table('priyasa_wallet_transactions')->whereBetween('created_at',[$from,$to])->orderBy('id')->limit(min($limit,10000))->get(['id','customer_id','wallet_id','amount','balance_after','type','reference_type','reference_id','created_at']);
    }

    public function loyalty(Carbon $from, Carbon $to, int $limit = 5000)
    {
        [$from,$to]=$this->range($from,$to);
        if(!Schema::connection('priyasa')->hasTable('priyasa_loyalty_transactions')) return collect();
        return DB::connection('priyasa')->table('priyasa_loyalty_transactions')->whereBetween('created_at',[$from,$to])->orderBy('id')->limit(min($limit,10000))->get(['id','customer_id','loyalty_account_id','points','balance_after','type','reference_type','reference_id','expires_at','created_at']);
    }

    public function closing(Carbon $date): array
    {
        $date=$date->startOfDay();
        $next=$date->copy()->addDay();
        $orders=DB::connection('priyasa')->table('priyasa_orders')->where('created_at','>=',$date)->where('created_at','<',$next);
        $gross=(float)(clone $orders)->whereNotIn('status',['cancelled'])->sum('grand_total');
        $discount=(float)(clone $orders)->sum('discount_total');
        $shipping=(float)(clone $orders)->sum('shipping_total');
        $refund=Schema::connection('priyasa')->hasTable('priyasa_refunds')?(float)DB::connection('priyasa')->table('priyasa_refunds')->where('created_at','>=',$date)->where('created_at','<',$next)->whereIn('status',['processed','completed','refunded'])->sum('amount'):0;
        $tax=Schema::connection('priyasa')->hasColumn('priyasa_order_items','tax_amount')?(float)DB::connection('priyasa')->table('priyasa_order_items')->where('created_at','>=',$date)->where('created_at','<',$next)->sum('tax_amount'):0;
        return ['date'=>$date->toDateString(),'gross_sales'=>round($gross,2),'discounts'=>round($discount,2),'shipping'=>round($shipping,2),'tax'=>round($tax,2),'refunds'=>round($refund,2),'net_sales'=>round($gross-$refund,2)];
    }
}
