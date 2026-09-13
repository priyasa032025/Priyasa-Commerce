<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Schema; use Modules\PriyasaCore\Http\Controllers\Controller;
final class OperationsController extends Controller {
 public function health(){return response()->json(['status'=>'ok','service'=>'priyasa-core','timestamp'=>now()->toISOString()]);}
 public function readiness(){ $checks=['database'=>false,'cache'=>false]; try{DB::connection('priyasa')->select('select 1');$checks['database']=true;}catch(\Throwable $e){} try{cache()->put('__priyasa_ready',1,5);$checks['cache']=cache()->get('__priyasa_ready')===1;}catch(\Throwable $e){} $ok=!in_array(false,$checks,true);return response()->json(['status'=>$ok?'ready':'not_ready','checks'=>$checks],$ok?200:503); }
 public function metrics(){return response()->json(['data'=>['pending_notifications'=>DB::connection('priyasa')->table('priyasa_notification_outbox')->where('status','pending')->count(),'failed_notifications'=>DB::connection('priyasa')->table('priyasa_notification_outbox')->where('status','failed')->count(),'failed_jobs'=>Schema::connection(config('database.default'))->hasTable('failed_jobs')?DB::connection(config('database.default'))->table('failed_jobs')->count():null,'orders_today'=>Schema::connection('priyasa')->hasTable('priyasa_orders')?DB::connection('priyasa')->table('priyasa_orders')->whereDate('created_at',today())->count():null]]);}
 public function audit(){ $q=DB::connection('priyasa')->table('priyasa_audit_logs')->orderByDesc('id');return response()->json(['data'=>$q->paginate(min(100,max(1,(int)request('per_page',50))))]); }
}
