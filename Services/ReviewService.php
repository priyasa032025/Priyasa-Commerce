<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\ProductReview;

final class ReviewService
{
    public function list(int $productId, ?Customer $customer, int $page=1, int $perPage=10, ?int $rating=null, string $sort='newest'): array
    {
        $page=max(1,$page); $perPage=max(1,min(50,$perPage));
        $q=ProductReview::query()->where('product_id',$productId)->where('status','approved');
        if ($rating !== null && $rating>=1 && $rating<=5) $q->where('rating',$rating);
        if ($sort==='helpful') $q->orderByDesc('helpful_count')->orderByDesc('created_at');
        elseif ($sort==='rating_high') $q->orderByDesc('rating')->orderByDesc('created_at');
        elseif ($sort==='rating_low') $q->orderBy('rating')->orderByDesc('created_at');
        else $q->orderByDesc('created_at');
        $total=(clone $q)->count();
        $rows=$q->forPage($page,$perPage)->get();
        $items=$rows->map(function($r) use($customer){
            $media=Schema::connection('priyasa')->hasTable('priyasa_review_media') ? DB::connection('priyasa')->table('priyasa_review_media')->where('review_id',$r->id)->orderBy('sort_order')->get()->map(fn($m)=>['id'=>$m->id,'url'=>$m->url,'type'=>$m->type])->all() : [];
            return ['id'=>$r->id,'rating'=>(int)$r->rating,'title'=>$r->title,'body'=>$r->body,'verified_purchase'=>(bool)$r->verified_purchase,'helpful_count'=>(int)$r->helpful_count,'not_helpful_count'=>(int)$r->not_helpful_count,'media'=>$media,'created_at'=>$r->created_at?->toISOString(),'mine'=>$customer ? (int)$r->customer_id===(int)$customer->getKey() : false];
        })->all();
        return ['items'=>$items,'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'last_page'=>max(1,(int)ceil($total/$perPage)),'has_more'=>$page*$perPage<$total],'summary'=>$this->summary($productId)];
    }

    public function summary(int $productId): array
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_product_rating_aggregates')) return ['average_rating'=>0,'review_count'=>0,'verified_review_count'=>0,'distribution'=>[1=>0,2=>0,3=>0,4=>0,5=>0]];
        $r=DB::connection('priyasa')->table('priyasa_product_rating_aggregates')->where('product_id',$productId)->first();
        if (!$r) return ['average_rating'=>0,'review_count'=>0,'verified_review_count'=>0,'distribution'=>[1=>0,2=>0,3=>0,4=>0,5=>0]];
        return ['average_rating'=>(float)$r->average_rating,'review_count'=>(int)$r->review_count,'verified_review_count'=>(int)$r->verified_review_count,'distribution'=>[1=>(int)$r->rating_1_count,2=>(int)$r->rating_2_count,3=>(int)$r->rating_3_count,4=>(int)$r->rating_4_count,5=>(int)$r->rating_5_count]];
    }

    public function create(Customer $customer, array $data): array
    {
        $productId=(int)$data['product_id']; $variantId=isset($data['variant_id'])?(int)$data['variant_id']:null;
        $orderId=isset($data['order_id'])?(int)$data['order_id']:null; $orderItemId=isset($data['order_item_id'])?(int)$data['order_item_id']:null;
        if (!$this->productExists($productId)) throw ValidationException::withMessages(['product_id'=>'Product not found.']);
        $verified=false;
        if ($orderItemId) {
            $item=DB::connection('priyasa')->table('priyasa_order_items')->where('id',$orderItemId)->first();
            if (!$item || (int)$item->variant_id !== (int)($variantId ?? $item->variant_id)) throw ValidationException::withMessages(['order_item_id'=>'Order item is invalid.']);
            $order=DB::connection('priyasa')->table('priyasa_orders')->where('id',$item->order_id)->where('customer_id',$customer->getKey())->first();
            if (!$order || !in_array((string)$order->status,['delivered','completed'],true)) throw ValidationException::withMessages(['order_item_id'=>'A delivered purchase is required for a verified review.']);
            if ($orderId && (int)$orderId !== (int)$order->id) throw ValidationException::withMessages(['order_id'=>'Order does not match order item.']);
            $orderId=(int)$order->id; $variantId=(int)$item->variant_id; $verified=true;
            if (ProductReview::where('customer_id',$customer->getKey())->where('order_item_id',$orderItemId)->exists()) throw ValidationException::withMessages(['review'=>'You already reviewed this purchased item.']);
        }
        $review=ProductReview::create(['product_id'=>$productId,'variant_id'=>$variantId,'customer_id'=>$customer->getKey(),'order_id'=>$orderId,'order_item_id'=>$orderItemId,'rating'=>(int)$data['rating'],'title'=>$data['title']??null,'body'=>$data['body']??null,'status'=>config('p42_reviews.auto_approve',false)?'approved':'pending','verified_purchase'=>$verified,'approved_at'=>config('p42_reviews.auto_approve',false)?now():null]);
        foreach (($data['media']??[]) as $i=>$url) if (Schema::connection('priyasa')->hasTable('priyasa_review_media')) DB::connection('priyasa')->table('priyasa_review_media')->insert(['review_id'=>$review->id,'url'=>(string)$url,'type'=>'image','sort_order'=>$i,'created_at'=>now(),'updated_at'=>now()]);
        if ($review->status==='approved') $this->rebuildAggregate($productId);
        return ['id'=>$review->id,'status'=>$review->status,'verified_purchase'=>(bool)$review->verified_purchase,'product_id'=>$productId];
    }

    public function vote(Customer $customer,int $reviewId,bool $helpful): array
    {
        $review=ProductReview::where('id',$reviewId)->where('status','approved')->firstOrFail();
        $old=DB::connection('priyasa')->table('priyasa_review_votes')->where('review_id',$reviewId)->where('customer_id',$customer->getKey())->first();
        DB::connection('priyasa')->transaction(function() use($review,$customer,$helpful,$old,$reviewId){
            if ($old) { if ((bool)$old->helpful === $helpful) return; DB::connection('priyasa')->table('priyasa_review_votes')->where('id',$old->id)->update(['helpful'=>$helpful,'updated_at'=>now()]); DB::connection('priyasa')->table('priyasa_product_reviews')->where('id',$reviewId)->update(['helpful_count'=>DB::connection('priyasa')->raw('GREATEST(helpful_count + '.($helpful?1:-1).',0)'),'not_helpful_count'=>DB::connection('priyasa')->raw('GREATEST(not_helpful_count + '.($helpful?-1:1).',0)')]); }
            else { try { DB::connection('priyasa')->table('priyasa_review_votes')->insert(['review_id'=>$reviewId,'customer_id'=>$customer->getKey(),'helpful'=>$helpful,'created_at'=>now(),'updated_at'=>now()]); DB::connection('priyasa')->table('priyasa_product_reviews')->where('id',$reviewId)->update([$helpful?'helpful_count':'not_helpful_count'=>DB::connection('priyasa')->raw(($helpful?'helpful_count':'not_helpful_count').' + 1')]); } catch (QueryException) {} }
        });
        $review->refresh(); return ['review_id'=>$reviewId,'helpful_count'=>(int)$review->helpful_count,'not_helpful_count'=>(int)$review->not_helpful_count];
    }

    public function moderate(int $id,string $status,?string $note=null): array
    {
        if (!in_array($status,['approved','rejected','pending'],true)) throw ValidationException::withMessages(['status'=>'Invalid moderation status.']);
        $r=ProductReview::findOrFail($id); $r->status=$status; $r->moderation_note=$note; $r->approved_at=$status==='approved'?now():null; $r->rejected_at=$status==='rejected'?now():null; $r->save(); $this->rebuildAggregate((int)$r->product_id); return ['id'=>$r->id,'status'=>$r->status];
    }

    public function rebuildAggregate(int $productId): void
    {
        $rows=ProductReview::where('product_id',$productId)->where('status','approved')->get(['rating','verified_purchase']);
        $count=$rows->count(); $verified=$rows->where('verified_purchase',true)->count(); $dist=[1=>0,2=>0,3=>0,4=>0,5=>0]; $sum=0;
        foreach($rows as $r){$rating=(int)$r->rating;if(isset($dist[$rating])){$dist[$rating]++;$sum+=$rating;}}
        DB::connection('priyasa')->table('priyasa_product_rating_aggregates')->updateOrInsert(['product_id'=>$productId],['review_count'=>$count,'verified_review_count'=>$verified,'average_rating'=>$count?round($sum/$count,2):0,'rating_1_count'=>$dist[1],'rating_2_count'=>$dist[2],'rating_3_count'=>$dist[3],'rating_4_count'=>$dist[4],'rating_5_count'=>$dist[5],'updated_at'=>now(),'created_at'=>now()]);
    }

    private function productExists(int $id): bool { return Schema::connection('priyasa')->hasTable('priyasa_products') && DB::connection('priyasa')->table('priyasa_products')->where('id',$id)->exists(); }
}
