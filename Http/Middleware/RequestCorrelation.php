<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Middleware;
use Closure; use Illuminate\Http\Request; use Illuminate\Support\Str; use Illuminate\Support\Facades\DB;
final class RequestCorrelation {
 public function handle(Request $request, Closure $next){$id=substr((string)$request->header('X-Request-ID'),0,100) ?: (string)Str::uuid();$request->headers->set('X-Request-ID',$id);$start=microtime(true);$response=$next($request);$response->headers->set('X-Request-ID',$id);try{DB::connection('priyasa')->table('priyasa_api_request_metrics')->insert(['request_id'=>$id,'method'=>$request->method(),'path'=>substr($request->path(),0,500),'status_code'=>$response->getStatusCode(),'duration_ms'=>(int)round((microtime(true)-$start)*1000),'user_id'=>optional($request->user())->id,'ip_address'=>$request->ip(),'created_at'=>now(),'updated_at'=>now()]);}catch(\Throwable $e){}return $response;}
}
