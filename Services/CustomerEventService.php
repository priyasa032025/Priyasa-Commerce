<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class CustomerEventService {
 public function track(Request $request, string $type, array $payload=[]): void {
  $user=$request->user(); $userId=$user?->getAuthIdentifier();
  DB::connection('priyasa')->table('priyasa_customer_events')->insert(['user_id'=>$userId ? (int)$userId : null,'customer_id'=>null,'session_id'=>$payload['session_id']??$request->header('X-Session-Id'),'event_type'=>$type,'product_id'=>$payload['product_id']??null,'variant_id'=>$payload['variant_id']??null,'query'=>$payload['query']??null,'metadata'=>json_encode($payload['metadata']??[]),'occurred_at'=>now()]);
 }
}
