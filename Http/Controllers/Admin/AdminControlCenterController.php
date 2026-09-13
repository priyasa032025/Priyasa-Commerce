<?php
namespace Modules\PriyasaCore\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\Product;

final class AdminControlCenterController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'data' => [
                'catalog' => $this->count('priyasa_products'),
                'customers' => $this->count('priyasa_customers'),
                'orders' => $this->count('priyasa_orders'),
                'returns' => $this->count('priyasa_returns'),
                'inventory' => [
                    'low_stock' => $this->inventoryCount('low'),
                    'out_of_stock' => $this->inventoryCount('out'),
                ],
                'payments' => [
                    'pending' => $this->paymentCount('pending'),
                    'failed' => $this->paymentCount('failed'),
                ],
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $q = Order::query()->with(['items','customer'])->latest();
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        if ($request->filled('payment_status')) $q->where('payment_status', $request->string('payment_status'));
        if ($request->filled('customer_id')) $q->where('customer_id', (int)$request->input('customer_id'));
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $q->where(function($w) use ($term){ $w->where('order_number','like',$term)->orWhere('id','like',$term); });
        }
        return response()->json(['data'=>$q->paginate(min(max((int)$request->input('per_page',25),1),100))]);
    }

    public function customers(Request $request): JsonResponse
    {
        $q = Customer::query()->latest();
        if ($request->filled('search')) {
            $term='%'.$request->input('search').'%';
            $q->where(fn($w)=>$w->where('name','like',$term)->orWhere('phone','like',$term)->orWhere('email','like',$term));
        }
        return response()->json(['data'=>$q->paginate(min(max((int)$request->input('per_page',25),1),100))]);
    }

    public function products(Request $request): JsonResponse
    {
        $q=Product::query()->with('variants')->latest();
        if ($request->filled('status')) $q->where('status',$request->string('status'));
        if ($request->filled('category_id')) $q->where('category_id',(int)$request->input('category_id'));
        if ($request->filled('brand_slug')) $q->where('brand_slug',$request->string('brand_slug'));
        if ($request->filled('search')) { $term='%'.$request->input('search').'%'; $q->where(fn($w)=>$w->where('name','like',$term)->orWhere('sku','like',$term)); }
        return response()->json(['data'=>$q->paginate(min(max((int)$request->input('per_page',25),1),100))]);
    }

    public function inventory(Request $request): JsonResponse
    {
        $table='priyasa_variants';
        if (!Schema::connection('priyasa')->hasTable($table)) return response()->json(['data'=>[]]);
        $q=DB::connection('priyasa')->table($table)->join('priyasa_inventory as i','i.variant_id','=',$table.'.id')->select($table.'.*','i.quantity','i.reserved_quantity','i.low_stock_threshold')->orderByDesc($table.'.updated_at');
        if ($request->boolean('out_of_stock')) $q->where('i.quantity','<=',0);
        if ($request->boolean('low_stock')) $q->whereColumn('i.quantity','<=','i.low_stock_threshold')->where('i.quantity','>',0);
        return response()->json(['data'=>$q->paginate(min(max((int)$request->input('per_page',50),1),200))]);
    }

    public function bulkPrice(Request $request): JsonResponse
    {
        $data=$request->validate(['variant_ids'=>'required|array|min:1|max:500','variant_ids.*'=>'integer','mode'=>'required|in:set,increase,decrease','amount'=>'required|numeric|min:0']);
        
        $job=DB::connection('priyasa')->table('priyasa_admin_bulk_jobs')->insertGetId(['operation'=>'bulk_price','status'=>'queued','created_by'=>optional($request->user())->id,'total'=>count($data['variant_ids']),'payload'=>json_encode($data),'created_at'=>now(),'updated_at'=>now()]);
        \Modules\PriyasaCore\Jobs\ProcessAdminBulkPrice::dispatch($job,$data);
        return response()->json(['data'=>['job_id'=>$job,'status'=>'queued']],202);
    }

    public function bulkJob(int $job): JsonResponse
    {
        return response()->json(['data'=>DB::connection('priyasa')->table('priyasa_admin_bulk_jobs')->find($job)]);
    }

    private function count(string $table): int { return Schema::connection('priyasa')->hasTable($table)?DB::connection('priyasa')->table($table)->count():0; }
    private function inventoryCount(string $mode): int {
        if (!Schema::connection('priyasa')->hasTable('priyasa_inventory')) return 0;
        $q=DB::connection('priyasa')->table('priyasa_inventory');
        return $mode==='out' ? $q->where('quantity','<=',0)->count() : $q->whereColumn('quantity','<=','low_stock_threshold')->where('quantity','>',0)->count();
    }
    private function paymentCount(string $status): int {
        if (!Schema::connection('priyasa')->hasTable('priyasa_payments') || !Schema::connection('priyasa')->hasColumn('priyasa_payments','status')) return 0;
        return DB::connection('priyasa')->table('priyasa_payments')->where('status',$status)->count();
    }
}
