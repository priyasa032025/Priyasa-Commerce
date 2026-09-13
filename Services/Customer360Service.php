<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\CustomerConsent;
use Modules\PriyasaCore\Models\CustomerNote;
use Modules\PriyasaCore\Models\CustomerSegment;
use Modules\PriyasaCore\Models\CustomerTag;

final class Customer360Service
{
    public function dashboard(array $filters=[]): array
    {
        $q=Customer::query()->orderByDesc('id');
        $term=trim((string)($filters['q']??''));
        if($term!=='') $q->where(function($x)use($term){foreach(['phone','mobile','email','name'] as $c) if(CustomerSchemaSafe::column('priyasa_customers',$c)) $x->orWhere($c,'like','%'.$term.'%');});
        $p=$q->paginate(min(100,max(1,(int)($filters['per_page']??25))));
        return ['items'=>$p->getCollection()->map(fn($c)=>$this->card($c))->values()->all(),'meta'=>['current_page'=>$p->currentPage(),'last_page'=>$p->lastPage(),'per_page'=>$p->perPage(),'total'=>$p->total()]];
    }

    public function profile(int $id): array
    {
        $c=Customer::query()->findOrFail($id);
        $user=method_exists($c,'user')?$c->user()->first():null;
        $orders=$this->count('priyasa_orders','customer_id',$id);
        $spend=$this->sum('priyasa_orders','customer_id',$id,['grand_total']);
        $wallet=$this->latestAggregate('priyasa_wallets','customer_id',$id,['balance','available_balance']);
        $loyalty=$this->latestAggregate('priyasa_loyalty_accounts','customer_id',$id,['points_balance','balance']);
        $devices=CustomerSchemaSafe::table('priyasa_notification_devices')?DB::connection('priyasa')->table('priyasa_notification_devices')->where('customer_id',$id)->get()->map(fn($d)=>(array)$d)->all():[];
        return ['customer'=>$this->card($c),'user'=>$user?->only(['id','name','email','phone','mobile']),'metrics'=>['orders'=>$orders,'lifetime_value'=>$spend,'reviews'=>$this->count('priyasa_product_reviews','customer_id',$id),'wishlist'=>$this->count('priyasa_wishlists','customer_id',$id),'support_tickets'=>$this->count('priyasa_support_tickets','customer_id',$id)],'wallet'=>$wallet,'loyalty'=>$loyalty,'tags'=>CustomerTag::where('customer_id',$id)->orderBy('tag')->get(['id','tag','value']),'segments'=>CustomerSegment::where('customer_id',$id)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->get(['id','segment','source','expires_at']),'consents'=>CustomerConsent::where('customer_id',$id)->get(['channel','purpose','granted','granted_at','revoked_at','source']),'notes'=>CustomerNote::where('customer_id',$id)->latest()->limit(50)->get(['id','note','author_id','created_at']),'devices'=>$devices,'identity_graph'=>$this->identityGraph($c),'timeline'=>$this->timeline($id)];
    }

    public function upsertTag(int $id,string $tag,?string $value): array { $this->exists($id); CustomerTag::updateOrCreate(['customer_id'=>$id,'tag'=>$tag],['value'=>$value]); return $this->profile($id); }
    public function removeTag(int $id,string $tag): array { CustomerTag::where('customer_id',$id)->where('tag',$tag)->delete(); return $this->profile($id); }
    public function addNote(int $id,string $note,?string $actor): array { $this->exists($id); CustomerNote::create(['customer_id'=>$id,'note'=>$note,'author_id'=>$actor]); return $this->profile($id); }
    public function consent(int $id,string $channel,string $purpose,bool $granted,?string $source): array { $this->exists($id); CustomerConsent::updateOrCreate(['customer_id'=>$id,'channel'=>$channel,'purpose'=>$purpose],['granted'=>$granted,'granted_at'=>$granted?now():DB::connection('priyasa')->raw('granted_at'),'revoked_at'=>$granted?null:now(),'source'=>$source]); return $this->profile($id); }
    public function segment(int $id,string $segment,?string $source,?string $expiresAt): array { $this->exists($id); CustomerSegment::updateOrCreate(['customer_id'=>$id,'segment'=>$segment],['source'=>$source?:'manual','expires_at'=>$expiresAt]); return $this->profile($id); }

    public function mergePreview(int $from,int $to): array
    {
        if($from===$to) throw ValidationException::withMessages(['customer'=>'Source and target customers must differ.']);
        $a=Customer::findOrFail($from); $b=Customer::findOrFail($to);
        $tables=[]; foreach(['priyasa_orders','priyasa_wishlists','priyasa_support_tickets','priyasa_product_reviews','priyasa_notification_devices','priyasa_customer_tags','priyasa_customer_notes','priyasa_customer_consents','priyasa_customer_segments','priyasa_customer_identity_links'] as $t) if(CustomerSchemaSafe::column($t,'customer_id')) $tables[$t]=DB::connection('priyasa')->table($t)->where('customer_id',$from)->count();
        return ['from'=>$this->card($a),'to'=>$this->card($b),'rows'=>$tables,'safe'=>'Only tables explicitly listed by this service are rewritten; customer identity records are never merged by guessing phone/email.'];
    }

    public function merge(int $from,int $to,?string $actor): array
    {
        if($from===$to) throw ValidationException::withMessages(['customer'=>'Source and target customers must differ.']);
        return DB::connection('priyasa')->transaction(function()use($from,$to,$actor){
            Customer::lockForUpdate()->findOrFail($from); Customer::lockForUpdate()->findOrFail($to);
            $moved=[];
            foreach(['priyasa_orders','priyasa_support_tickets','priyasa_product_reviews','priyasa_notification_devices','priyasa_customer_notes','priyasa_customer_consents','priyasa_customer_segments','priyasa_customer_identity_links'] as $t) if(CustomerSchemaSafe::column($t,'customer_id')) { $n=DB::connection('priyasa')->table($t)->where('customer_id',$from)->update(['customer_id'=>$to]); $moved[$t]=$n; }
            // P43 stores customer ownership on wishlist headers and products on wishlist items.
            if (CustomerSchemaSafe::table('priyasa_wishlists') && CustomerSchemaSafe::table('priyasa_wishlist_items')) {
                $source = DB::connection('priyasa')->table('priyasa_wishlists')->where('customer_id',$from)->first();
                $target = DB::connection('priyasa')->table('priyasa_wishlists')->where('customer_id',$to)->first();
                if ($source) {
                    if (!$target) {
                        DB::connection('priyasa')->table('priyasa_wishlists')->where('id',$source->id)->update(['customer_id'=>$to]);
                        $target = (object)['id'=>$source->id];
                    }
                    $movedItems=0;
                    $items=DB::connection('priyasa')->table('priyasa_wishlist_items')->where('wishlist_id',$source->id)->get();
                    foreach($items as $item) {
                        $exists=DB::connection('priyasa')->table('priyasa_wishlist_items')->where('wishlist_id',$target->id)->where('product_id',$item->product_id)->where(function($q) use ($item){ if($item->variant_id===null) $q->whereNull('variant_id'); else $q->where('variant_id',$item->variant_id); })->exists();
                        if($exists) DB::connection('priyasa')->table('priyasa_wishlist_items')->where('id',$item->id)->delete();
                        else { DB::connection('priyasa')->table('priyasa_wishlist_items')->where('id',$item->id)->update(['wishlist_id'=>$target->id]); $movedItems++; }
                    }
                    if ($source->id !== $target->id) DB::connection('priyasa')->table('priyasa_wishlists')->where('id',$source->id)->delete();
                    $moved['priyasa_wishlist_items']=$movedItems;
                }
            }
            DB::connection('priyasa')->table('priyasa_customer_merge_events')->insert(['from_customer_id'=>$from,'to_customer_id'=>$to,'actor_id'=>$actor,'summary'=>json_encode($moved),'created_at'=>now(),'updated_at'=>now()]);
            return ['merged'=>true,'from_customer_id'=>$from,'to_customer_id'=>$to,'moved'=>$moved];
        });
    }

    private function exists(int $id):void{if(!Customer::whereKey($id)->exists())throw ValidationException::withMessages(['customer'=>'Customer not found.']);}
    private function card($c):array{return ['id'=>$c->id,'name'=>$this->field($c,['name','full_name','first_name']),'email'=>$this->field($c,['email']),'phone'=>$this->field($c,['phone','mobile']),'created_at'=>$c->created_at?->toISOString(),'updated_at'=>$c->updated_at?->toISOString()];}
    private function field($m,array $names){foreach($names as $n)if(isset($m->{$n}))return $m->{$n};return null;}
    private function count(string $t,string $col,int $id):int{return CustomerSchemaSafe::column($t,$col)?(int)DB::connection('priyasa')->table($t)->where($col,$id)->count():0;}
    private function sum(string $t,string $col,int $id,array $fields):float{foreach($fields as $f)if(CustomerSchemaSafe::column($t,$f))return (float)DB::connection('priyasa')->table($t)->where($col,$id)->sum($f);return 0.0;}
    private function latestAggregate(string $t,string $col,int $id,array $fields):?array{if(!CustomerSchemaSafe::table($t)||!CustomerSchemaSafe::column($t,$col))return null;$r=DB::connection('priyasa')->table($t)->where($col,$id)->latest('id')->first();if(!$r)return null;$a=['id'=>$r->id??null];foreach($fields as $f)if(isset($r->{$f}))$a[$f]=$r->{$f};return $a;}
    private function identityGraph($c):array{$out=[];foreach([['customer_id',(string)$c->id,true]] as $x)$out[]=['type'=>$x[0],'value'=>$x[1],'verified'=>$x[2]];foreach(['phone','mobile','email'] as $f)if(isset($c->{$f})&&$c->{$f})$out[]=['type'=>$f,'value'=>$c->{$f},'verified'=>false];if(CustomerSchemaSafe::table('priyasa_customer_identity_links'))$out=array_merge($out,DB::connection('priyasa')->table('priyasa_customer_identity_links')->where('customer_id',$c->id)->get()->map(fn($x)=>['type'=>$x->identity_type,'value'=>$x->identity_value,'verified'=>(bool)$x->verified])->all());return $out;}
    private function timeline(int $id):array{$out=[];foreach(['priyasa_orders','priyasa_support_tickets','priyasa_product_reviews','priyasa_customer_notes'] as $t)if(CustomerSchemaSafe::column($t,'customer_id'))$out=array_merge($out,DB::connection('priyasa')->table($t)->where('customer_id',$id)->orderByDesc('created_at')->limit(20)->get()->map(fn($r)=>['source'=>$t,'id'=>$r->id,'created_at'=>$r->created_at??null])->all());usort($out,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));return array_slice($out,0,50);}
}

final class CustomerSchemaSafe {
    public static function table(string $table): bool { try { return DB::connection('priyasa')->getSchemaBuilder()->hasTable($table); } catch (\Throwable) { return false; } }
    public static function column(string $table,string $column): bool { try { return self::table($table) && DB::connection('priyasa')->getSchemaBuilder()->hasColumn($table,$column); } catch (\Throwable) { return false; } }
}
