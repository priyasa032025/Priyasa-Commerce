<?php
namespace Modules\PriyasaCore\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\PriyasaCore\Models\CommerceConversation;
use Modules\PriyasaCore\Models\CommerceConversationMessage;
use Modules\PriyasaCore\Services\MetaWhatsAppService;

final class SendWhatsAppOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries=4;
    public function __construct(public string $phone, public string $body, public string $orderId, public string $event) {}
    public function backoff(): array { return [30,120,600]; }
    public function handle(MetaWhatsAppService $meta): void {
        $conversation=CommerceConversation::firstOrCreate(['channel'=>'whatsapp','external_id'=>$this->phone],['phone'=>$this->phone,'metadata'=>['system'=>'order_notifications']]);
        $message=CommerceConversationMessage::create(['conversation_id'=>$conversation->id,'direction'=>'outbound','message'=>$this->body,'metadata'=>['order_id'=>$this->orderId,'event'=>$this->event,'system_notification'=>true]]);
        try {
            $r=$meta->sendText($this->phone,$this->body);
            $message->update(['provider_message_id'=>$r['messages'][0]['id']??null,'provider_status'=>'sent','sent_at'=>now(),'metadata'=>array_merge((array)$message->metadata,['provider_response'=>$r])]);
        } catch (\Throwable $e) { $message->update(['provider_status'=>'failed','metadata'=>array_merge((array)$message->metadata,['error'=>$e->getMessage()])]); throw $e; }
    }
}
