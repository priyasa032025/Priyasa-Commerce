<?php
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Str;
use Modules\PriyasaCore\Models\CommerceConversation;
use Modules\PriyasaCore\Models\CommerceConversationMessage;
use Modules\PriyasaCore\Models\Order;

final class CommerceAgentService
{
 public function __construct(private CommerceAIService $ai) {}

 public function handle(string $channel,string $externalId,string $text,?int $customerId=null): array
 {
  $conversation=CommerceConversation::firstOrCreate(['channel'=>$channel,'external_id'=>$externalId],['customer_id'=>$customerId,'status'=>'open','last_message_at'=>now()]);
  if($customerId && !$conversation->customer_id) $conversation->update(['customer_id'=>$customerId]);
  CommerceConversationMessage::create(['conversation_id'=>$conversation->id,'direction'=>'inbound','message'=>$text,'intent'=>null,'metadata'=>[]]);
  $intent=$this->ai->classifyIntent($text);
  $reply=$this->safeTool($intent['intent'],$text,$conversation->customer_id);
  CommerceConversationMessage::create(['conversation_id'=>$conversation->id,'direction'=>'outbound','message'=>$reply,'intent'=>$intent['intent'],'metadata'=>['confidence'=>$intent['confidence']]]);
  $conversation->update(['last_message_at'=>now()]);
  return ['conversation_id'=>$conversation->id,'intent'=>$intent,'reply'=>$reply,'handoff'=>Str::contains(Str::lower($text),['agent','human','support person'])];
 }

 private function safeTool(string $intent,string $text,?int $customerId): string
 {
  if($intent==='order_status') {
   if(!$customerId) return 'Please verify your account first so I can securely check your order.';
   $order=Order::where('customer_id',$customerId)->latest('id')->first();
   return $order ? 'Your latest order is currently '.$order->status.'.' : 'I could not find an order on your account.';
  }
  if($intent==='return') return $customerId ? 'I can help with returns, refunds, or exchanges. Please provide the order number.' : 'Please verify your account first so I can securely assist with a return or refund.';
  if($intent==='payment') return 'I can help check payment status. Please provide the order number after account verification.';
  if($intent==='product') return 'I can help with product, size, colour, material, and availability questions. Tell me the product or variant you are looking for.';
  return 'I can help with orders, products, payments, returns, refunds, and exchanges. What would you like to do?';
 }
}
