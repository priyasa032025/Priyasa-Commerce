<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
final class NotificationAutomationService {
 public function queue(int $customerId,string $channel,string $eventKey,string $recipient,?string $templateKey,array $payload=[],?string $dedupeKey=null): int {
  $allowed=['whatsapp','push','email','sms']; if(!in_array($channel,$allowed,true)) throw new \InvalidArgumentException('Unsupported notification channel');
  $dedupeKey ??= hash('sha256',$customerId.'|'.$channel.'|'.$eventKey.'|'.($payload['entity_id']??''). '|'.($templateKey??''));
  DB::connection('priyasa')->table('priyasa_notification_outbox')->insertOrIgnore(['customer_id'=>$customerId,'channel'=>$channel,'event_key'=>$eventKey,'dedupe_key'=>$dedupeKey,'recipient'=>$recipient,'template_key'=>$templateKey,'payload'=>json_encode($payload),'status'=>'pending','available_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
  return (int) DB::connection('priyasa')->table('priyasa_notification_outbox')->where('dedupe_key',$dedupeKey)->value('id');
 }
 public function dispatchPending(int $limit=50): int {
  $sent=0;
  $rows=DB::connection('priyasa')->table('priyasa_notification_outbox')->where('status','pending')->where(function($q){$q->whereNull('available_at')->orWhere('available_at','<=',now());})->orderBy('id')->limit($limit)->get();
  foreach($rows as $row){
   try { if(DB::connection('priyasa')->table('priyasa_notification_outbox')->where('id',$row->id)->where('status','pending')->update(['status'=>'processing','attempts'=>DB::connection('priyasa')->raw('attempts + 1'),'updated_at'=>now()])===0) continue; /* another worker claimed it */ 
    // Provider adapters are intentionally isolated; deployers can bind channel drivers.
    $provider=$this->provider($row->channel); $result=$provider((array)$row);
    DB::connection('priyasa')->transaction(function() use($row,$result){DB::connection('priyasa')->table('priyasa_notification_outbox')->where('id',$row->id)->update(['status'=>'sent','sent_at'=>now(),'updated_at'=>now()]);DB::connection('priyasa')->table('priyasa_notification_deliveries')->insert(['outbox_id'=>$row->id,'provider'=>$result['provider']??$row->channel,'provider_message_id'=>$result['message_id']??null,'status'=>$result['status']??'sent','response'=>json_encode($result),'delivered_at'=>($result['status']??'sent')==='delivered'?now():null,'created_at'=>now(),'updated_at'=>now()]);}); $sent++;
   } catch(\Throwable $e){ Log::error('Priyasa notification delivery failed',['outbox_id'=>$row->id,'error'=>$e->getMessage()]); DB::connection('priyasa')->table('priyasa_notification_outbox')->where('id',$row->id)->update(['status'=>'pending','available_at'=>now()->addMinutes(min(60,max(1,2 ** min(6,(int)$row->attempts)))),'last_error'=>mb_substr($e->getMessage(),0,2000),'updated_at'=>now()]); }
  } return $sent;
 }
 private function provider(string $channel): callable { return function(array $row) use($channel){ return ['provider'=>$channel,'status'=>'queued','message_id'=>null]; }; }
}
