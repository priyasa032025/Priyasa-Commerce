<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\OrderItem;
use Modules\PriyasaCore\Models\SupportEvent;
use Modules\PriyasaCore\Models\SupportMessage;
use Modules\PriyasaCore\Models\SupportTicket;
use RuntimeException;

final class CustomerSupportService
{
    private const CATEGORIES = ['order','payment','delivery','return','refund','exchange','product','account','other'];
    private const STATUSES = ['open','in_progress','waiting_customer','waiting_internal','resolved','closed'];
    private const PRIORITIES = ['low','normal','high','urgent'];

    public function categories(): array { return self::CATEGORIES; }

    public function list(Customer $customer, int $perPage = 20): array
    {
        $p = SupportTicket::query()->where('customer_id',$customer->id)->latest('updated_at')->paginate(min(50,max(1,$perPage)));
        return ['items'=>$p->getCollection()->map(fn($t)=>$this->present($t))->values()->all(),'meta'=>['current_page'=>$p->currentPage(),'last_page'=>$p->lastPage(),'per_page'=>$p->perPage(),'total'=>$p->total()]];
    }

    public function detail(Customer $customer, int $id): array
    {
        $t = SupportTicket::query()->where('customer_id',$customer->id)->with(['messages'=>fn($q)=>$q->orderBy('id')])->findOrFail($id);
        $t->messages()->where('sender_type','admin')->whereNull('read_at')->update(['read_at'=>now()]);
        return $this->present($t,true);
    }

    public function create(Customer $customer, array $data): array
    {
        $category = strtolower(trim((string)($data['category'] ?? 'other')));
        if (!in_array($category,self::CATEGORIES,true)) throw new RuntimeException('Invalid support category.');
        $message = trim((string)($data['message'] ?? ''));
        if ($message === '') throw new RuntimeException('Message is required.');
        [$order,$item] = $this->resolveOrderContext($customer,$data);
        return DB::connection('priyasa')->transaction(function() use ($customer,$data,$category,$message,$order,$item) {
            $ticket = SupportTicket::create([
                'customer_id'=>$customer->id,'order_id'=>$order?->id,'order_item_id'=>$item?->id,
                'ticket_number'=>$this->number(),'category'=>$category,
                'priority'=>in_array(($data['priority'] ?? 'normal'),self::PRIORITIES,true)?$data['priority']:'normal',
                'status'=>'open','subject'=>trim((string)($data['subject'] ?? ucfirst($category).' assistance')),
                'last_message'=>$message,'last_message_at'=>now(),'metadata'=>$data['metadata'] ?? null,
            ]);
            SupportMessage::create(['ticket_id'=>$ticket->id,'sender_type'=>'customer','sender_id'=>(string)$customer->id,'message'=>$message,'attachments'=>$data['attachments'] ?? null]);
            $this->event($ticket,'created','customer',(string)$customer->id,null,'open');
            return $this->present($ticket->fresh(['messages']),true);
        });
    }

    public function reply(Customer $customer, int $id, array $data): array
    {
        $message = trim((string)($data['message'] ?? ''));
        if ($message === '') throw new RuntimeException('Message is required.');
        return DB::connection('priyasa')->transaction(function() use ($customer,$id,$message,$data) {
            $ticket=SupportTicket::where('customer_id',$customer->id)->lockForUpdate()->findOrFail($id);
            if (in_array($ticket->status,['resolved','closed'],true)) throw new RuntimeException('This ticket is closed. Please create a new ticket.');
            SupportMessage::create(['ticket_id'=>$ticket->id,'sender_type'=>'customer','sender_id'=>(string)$customer->id,'message'=>$message,'attachments'=>$data['attachments'] ?? null]);
            $ticket->update(['status'=>'open','last_message'=>$message,'last_message_at'=>now()]);
            $this->event($ticket,'customer_replied','customer',(string)$customer->id,null,'open');
            return $this->present($ticket->fresh(['messages']),true);
        });
    }

    public function close(Customer $customer, int $id): array
    {
        return DB::connection('priyasa')->transaction(function() use ($customer,$id) {
            $ticket=SupportTicket::where('customer_id',$customer->id)->lockForUpdate()->findOrFail($id);
            if ($ticket->status !== 'closed') {
                $from=$ticket->status; $ticket->update(['status'=>'closed','closed_at'=>now()]);
                $this->event($ticket,'status_changed','customer',(string)$customer->id,$from,'closed');
            }
            return $this->present($ticket->fresh(['messages']),true);
        });
    }

    public function adminList(array $filters=[]): array
    {
        $q=SupportTicket::query()->with('customer')->latest('updated_at');
        foreach (['status','priority','category','assigned_to'] as $field) if(isset($filters[$field]) && $filters[$field]!=='') $q->where($field,$filters[$field]);
        if (!empty($filters['q'])) $q->where(fn($x)=>$x->where('ticket_number','like','%'.$filters['q'].'%')->orWhere('subject','like','%'.$filters['q'].'%'));
        $p=$q->paginate(min(100,max(1,(int)($filters['per_page'] ?? 25))));
        return ['items'=>$p->getCollection()->map(fn($t)=>$this->present($t))->values()->all(),'meta'=>['current_page'=>$p->currentPage(),'last_page'=>$p->lastPage(),'total'=>$p->total()]];
    }

    public function adminUpdate(int $id,array $data,string $actorId=''): array
    {
        return DB::connection('priyasa')->transaction(function() use ($id,$data,$actorId) {
            $t=SupportTicket::lockForUpdate()->findOrFail($id); $from=$t->status;
            if(isset($data['status']) && !in_array($data['status'],self::STATUSES,true)) throw new RuntimeException('Invalid ticket status.');
            if(isset($data['priority']) && !in_array($data['priority'],self::PRIORITIES,true)) throw new RuntimeException('Invalid ticket priority.');
            $updates=[]; foreach(['status','priority','assigned_to'] as $f) if(array_key_exists($f,$data)) $updates[$f]=$data[$f];
            if(($updates['status'] ?? null)==='resolved') $updates['resolved_at']=now();
            if(($updates['status'] ?? null)==='closed') $updates['closed_at']=now();
            $t->update($updates);
            if(isset($updates['status']) && $from!==$updates['status']) $this->event($t,'status_changed','admin',$actorId,$from,$updates['status']);
            if(array_key_exists('assigned_to',$updates)) $this->event($t,'assigned','admin',$actorId,null,(string)$updates['assigned_to']);
            return $this->present($t->fresh(['messages']),true);
        });
    }

    public function adminReply(int $id,array $data,string $actorId=''): array
    {
        $message=trim((string)($data['message'] ?? '')); if($message==='') throw new RuntimeException('Message is required.');
        return DB::connection('priyasa')->transaction(function() use($id,$data,$actorId,$message){
            $t=SupportTicket::lockForUpdate()->findOrFail($id);
            if($t->status==='closed') throw new RuntimeException('Closed ticket cannot receive replies.');
            SupportMessage::create(['ticket_id'=>$t->id,'sender_type'=>'admin','sender_id'=>$actorId?:null,'message'=>$message,'attachments'=>$data['attachments'] ?? null]);
            $updates=['status'=>'waiting_customer','last_message'=>$message,'last_message_at'=>now()]; if(!$t->first_response_at)$updates['first_response_at']=now(); $t->update($updates);
            $this->event($t,'admin_replied','admin',$actorId,null,'waiting_customer');
            return $this->present($t->fresh(['messages']),true);
        });
    }

    private function resolveOrderContext(Customer $customer,array $data): array
    {
        $order=null; $item=null;
        if(!empty($data['order_id'])) $order=Order::where('id',$data['order_id'])->where('customer_id',$customer->id)->firstOrFail();
        if(!empty($data['order_item_id'])) {
            $item=OrderItem::where('id',$data['order_item_id'])->whereHas('order',fn($q)=>$q->where('customer_id',$customer->id))->firstOrFail();
            if($order && $item->order_id !== $order->id) throw new RuntimeException('Order item does not belong to the selected order.');
            $order=$order ?: $item->order;
        }
        return [$order,$item];
    }
    private function number(): string { do {$n='PRI-SUP-'.now()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8));} while(SupportTicket::where('ticket_number',$n)->exists()); return $n; }
    private function event(SupportTicket $t,string $type,string $actorType,?string $actorId,?string $from,?string $to): void { SupportEvent::create(['ticket_id'=>$t->id,'event_type'=>$type,'actor_type'=>$actorType,'actor_id'=>$actorId,'from_value'=>$from,'to_value'=>$to]); }
    private function present(SupportTicket $t,bool $detail=false): array {
        $a=['id'=>$t->id,'ticket_number'=>$t->ticket_number,'category'=>$t->category,'priority'=>$t->priority,'status'=>$t->status,'subject'=>$t->subject,'order_id'=>$t->order_id,'order_item_id'=>$t->order_item_id,'assigned_to'=>$t->assigned_to,'last_message'=>$t->last_message,'last_message_at'=>$t->last_message_at?->toISOString(),'created_at'=>$t->created_at?->toISOString(),'resolved_at'=>$t->resolved_at?->toISOString(),'closed_at'=>$t->closed_at?->toISOString()];
        if($detail) $a['messages']=$t->relationLoaded('messages')?$t->messages->map(fn($m)=>['id'=>$m->id,'sender_type'=>$m->sender_type,'sender_id'=>$m->sender_id,'message'=>$m->message,'attachments'=>$m->attachments,'created_at'=>$m->created_at?->toISOString()])->values()->all():[];
        return $a;
    }
}
