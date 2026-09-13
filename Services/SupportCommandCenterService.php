<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\PriyasaCore\Models\CommerceConversation;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\PaymentTransaction;
use Modules\PriyasaCore\Models\Refund;
use Modules\PriyasaCore\Models\ReturnRequest;
use Modules\PriyasaCore\Models\Shipment;
use Modules\PriyasaCore\Models\SupportAction;
use Modules\PriyasaCore\Models\SupportTicket;
use Modules\PriyasaCore\Models\Customer;

final class SupportCommandCenterService
{
    private const RESOLUTION_CODES = [
        'resolved_information','resolved_order','resolved_payment','resolved_delivery',
        'resolved_return','resolved_refund','resolved_exchange','resolved_product',
        'resolved_account','resolved_other','duplicate','customer_unresponsive',
    ];

    public function dashboard(array $filters = []): array
    {
        $q = SupportTicket::query();
        foreach (['status','priority','category','assigned_to'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') $q->where($field, $filters[$field]);
        }
        if (!empty($filters['q'])) {
            $needle = trim((string)$filters['q']);
            $q->where(fn($x) => $x->where('ticket_number','like','%'.$needle.'%')->orWhere('subject','like','%'.$needle.'%'));
        }
        $total = (clone $q)->count();
        $open = (clone $q)->whereIn('status',['open','in_progress','waiting_customer','waiting_internal'])->count();
        $urgent = (clone $q)->whereIn('priority',['urgent','high'])->whereNotIn('status',['closed'])->count();
        $overdue = SupportSchemaSafe::column('priyasa_support_tickets','sla_due_at')
            ? (clone $q)->whereNotNull('sla_due_at')->where('sla_due_at','<',now())->whereNotIn('status',['resolved','closed'])->count() : 0;
        return ['counts'=>['total'=>$total,'open'=>$open,'urgent_or_high'=>$urgent,'sla_overdue'=>$overdue], 'resolution_codes'=>self::RESOLUTION_CODES];
    }

    public function ticket(int $ticketId): array
    {
        $ticket = SupportTicket::query()->with(['customer','order','messages','events'])->findOrFail($ticketId);
        return ['ticket'=>$this->ticketCard($ticket), 'command_center'=>$this->aggregate($ticket->order_id), 'actions'=>SupportAction::where('ticket_id',$ticket->id)->latest()->get()->map(fn($a)=>$this->action($a))->values()->all()];
    }

    public function orderContext(int $orderId): array
    {
        $order = Order::with(['customer','items','paymentTransactions'])->findOrFail($orderId);
        return $this->aggregate($order->id, $order);
    }

    public function link(int $ticketId, array $data, string $actorId=''): array
    {
        return DB::connection('priyasa')->transaction(function () use ($ticketId,$data,$actorId) {
            $ticket = SupportTicket::lockForUpdate()->findOrFail($ticketId);
            $allowed = ['return_id','refund_id','shipment_id','payment_transaction_id','conversation_id'];
            $updates=[];
            foreach ($allowed as $field) if (array_key_exists($field,$data)) $updates[$field] = $data[$field] !== null ? (int)$data[$field] : null;
            if (!$updates) throw ValidationException::withMessages(['link'=>'At least one supported entity link is required.']);
            $this->validateLinks($ticket, $updates);
            $ticket->update($updates);
            foreach ($updates as $field=>$id) {
                SupportAction::create(['ticket_id'=>$ticket->id,'action'=>'link_'.$field,'actor_id'=>$actorId ?: null,'entity_type'=>$field,'entity_id'=>$id,'payload'=>['value'=>$id]]);
            }
            return $this->ticket($ticket->id);
        });
    }

    public function resolve(int $ticketId, string $code, string $actorId=''): array
    {
        if (!in_array($code,self::RESOLUTION_CODES,true)) throw ValidationException::withMessages(['resolution_code'=>'Unsupported resolution code.']);
        return DB::connection('priyasa')->transaction(function() use($ticketId,$code,$actorId){
            $t=SupportTicket::lockForUpdate()->findOrFail($ticketId);
            if ($t->status==='closed') throw ValidationException::withMessages(['ticket'=>'Closed ticket cannot be resolved again.']);
            $from=$t->status;
            $t->update(['status'=>'resolved','resolution_code'=>$code,'resolved_at'=>now(),'closed_at'=>null]);
            SupportAction::create(['ticket_id'=>$t->id,'action'=>'resolve','actor_id'=>$actorId ?: null,'payload'=>['from_status'=>$from,'resolution_code'=>$code]]);
            return $this->ticket($t->id);
        });
    }

    public function reopen(int $ticketId, string $actorId='', ?string $reason=null): array
    {
        return DB::connection('priyasa')->transaction(function() use($ticketId,$actorId,$reason){
            $t=SupportTicket::lockForUpdate()->findOrFail($ticketId);
            $from=$t->status;
            $t->update(['status'=>'in_progress','closed_at'=>null,'resolved_at'=>null]);
            SupportAction::create(['ticket_id'=>$t->id,'action'=>'reopen','actor_id'=>$actorId ?: null,'payload'=>['from_status'=>$from,'reason'=>$reason]]);
            return $this->ticket($t->id);
        });
    }

    public function notifyCustomer(int $ticketId, string $event, string $actorId=''): array
    {
        if (!in_array($event, OrderNotificationService::supportedEvents(), true)) throw ValidationException::withMessages(['event'=>'Unsupported customer notification event.']);
        $ticket=SupportTicket::with('order')->findOrFail($ticketId);
        if (!$ticket->order) throw ValidationException::withMessages(['order'=>'Ticket has no linked order.']);
        $inbox=app(OrderNotificationService::class)->dispatch($ticket->order,$event,['support_ticket_id'=>(string)$ticket->id,'manual'=>true]);
        SupportAction::create(['ticket_id'=>$ticket->id,'action'=>'notify_customer','actor_id'=>$actorId ?: null,'entity_type'=>'order','entity_id'=>$ticket->order_id,'payload'=>['event'=>$event,'notification_id'=>$inbox?->id]]);
        return ['sent'=>(bool)$inbox,'notification_id'=>$inbox?->id,'event'=>$event];
    }

    private function aggregate(int $orderId, ?Order $order=null): array
    {
        $order ??= Order::with(['customer','items','paymentTransactions'])->find($orderId);
        if (!$order) return ['order'=>null,'returns'=>[],'refunds'=>[],'shipments'=>[],'payments'=>[],'whatsapp'=>[],'timeline'=>[]];
        $returns=DB::connection('priyasa')->table('priyasa_returns')->where('order_id',$orderId)->orderByDesc('id')->get()->map(fn($r)=>(array)$r)->all();
        $refunds=DB::connection('priyasa')->table('priyasa_refunds')->where('order_id',$orderId)->orderByDesc('id')->get()->map(fn($r)=>(array)$r)->all();
        $shipments=DB::connection('priyasa')->table('priyasa_shipments')->where('order_id',$orderId)->orderByDesc('id')->get()->map(fn($r)=>(array)$r)->all();
        $payments=DB::connection('priyasa')->table('priyasa_payment_transactions')->where('order_id',$orderId)->orderByDesc('id')->get()->map(fn($p)=>(array)$p)->all();
        $whatsapp=[];
        $customerId=$order->customer_id ?? null;
        if ($customerId && SupportSchemaSafe::table('priyasa_commerce_conversations')) {
            $whatsapp=CommerceConversation::query()->where('customer_id',$customerId)->with('messages')->latest('last_message_at')->limit(10)->get()->map(fn($c)=>['id'=>$c->id,'channel'=>$c->channel,'external_id'=>$c->external_id,'status'=>$c->status,'last_message_at'=>$c->last_message_at?->toISOString()])->all();
        }
        $timeline=DB::connection('priyasa')->table('priyasa_order_status_history')->where('order_id',$orderId)->orderBy('created_at')->get()->map(fn($h)=>(array)$h)->all();
        return ['order'=>$this->orderCard($order),'returns'=>$returns,'refunds'=>$refunds,'shipments'=>$shipments,'payments'=>$payments,'whatsapp'=>$whatsapp,'timeline'=>$timeline];
    }

    private function validateLinks(SupportTicket $ticket,array $updates): void
    {
        if (isset($updates['return_id']) && $updates['return_id'] && !DB::connection('priyasa')->table('priyasa_returns')->where('id',$updates['return_id'])->where('order_id',$ticket->order_id)->exists()) throw ValidationException::withMessages(['return_id'=>'Return does not belong to the ticket order.']);
        if (isset($updates['refund_id']) && $updates['refund_id'] && !DB::connection('priyasa')->table('priyasa_refunds')->where('id',$updates['refund_id'])->where('order_id',$ticket->order_id)->exists()) throw ValidationException::withMessages(['refund_id'=>'Refund does not belong to the ticket order.']);
        if (isset($updates['shipment_id']) && $updates['shipment_id'] && !DB::connection('priyasa')->table('priyasa_shipments')->where('id',$updates['shipment_id'])->where('order_id',$ticket->order_id)->exists()) throw ValidationException::withMessages(['shipment_id'=>'Shipment does not belong to the ticket order.']);
        if (isset($updates['payment_transaction_id']) && $updates['payment_transaction_id'] && !DB::connection('priyasa')->table('priyasa_payment_transactions')->where('id',$updates['payment_transaction_id'])->where('order_id',$ticket->order_id)->exists()) throw ValidationException::withMessages(['payment_transaction_id'=>'Payment transaction does not belong to the ticket order.']);
    }

    private function ticketCard(SupportTicket $t): array { return ['id'=>$t->id,'ticket_number'=>$t->ticket_number,'category'=>$t->category,'priority'=>$t->priority,'status'=>$t->status,'subject'=>$t->subject,'customer_id'=>$t->customer_id,'order_id'=>$t->order_id,'order_item_id'=>$t->order_item_id,'return_id'=>$t->return_id,'refund_id'=>$t->refund_id,'shipment_id'=>$t->shipment_id,'payment_transaction_id'=>$t->payment_transaction_id,'conversation_id'=>$t->conversation_id,'assigned_to'=>$t->assigned_to,'resolution_code'=>$t->resolution_code,'sla_due_at'=>$t->sla_due_at?->toISOString(),'first_response_at'=>$t->first_response_at?->toISOString(),'resolved_at'=>$t->resolved_at?->toISOString(),'closed_at'=>$t->closed_at?->toISOString()]; }
    private function orderCard(Order $o): array { return ['id'=>$o->id,'order_number'=>$o->order_number,'status'=>$o->status,'payment_status'=>$o->payment_status,'payment_method'=>$o->payment_method,'currency'=>$o->currency,'subtotal'=>(string)$o->subtotal,'discount_total'=>(string)$o->discount_total,'tax_total'=>(string)$o->tax_total,'shipping_total'=>(string)$o->shipping_total,'grand_total'=>(string)$o->grand_total,'customer_id'=>$o->customer_id,'placed_at'=>$o->placed_at?->toISOString()]; }
    private function action(SupportAction $a): array { return ['id'=>$a->id,'action'=>$a->action,'actor_id'=>$a->actor_id,'entity_type'=>$a->entity_type,'entity_id'=>$a->entity_id,'payload'=>$a->payload,'created_at'=>$a->created_at?->toISOString()]; }
}

final class SupportSchemaSafe
{
    public static function table(string $table): bool { try { return DB::connection('priyasa')->getSchemaBuilder()->hasTable($table); } catch (\Throwable) { return false; } }
    public static function column(string $table,string $column): bool { try { return self::table($table) && DB::connection('priyasa')->getSchemaBuilder()->hasColumn($table,$column); } catch (\Throwable) { return false; } }
}
