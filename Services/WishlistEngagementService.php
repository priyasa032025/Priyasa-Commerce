<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WishlistEngagementService
{
    public function list(?int $customerId, ?string $sessionId): array
    {
        $wishlist = $this->wishlist($customerId, $sessionId, false);
        if (!$wishlist) return ['items'=>[], 'count'=>0, 'session_id'=>$sessionId];
        $items = DB::connection('priyasa')->table('priyasa_wishlist_items as wi')
            ->leftJoin('priyasa_products as p','p.id','=','wi.product_id')
            ->leftJoin('priyasa_product_variants as v','v.id','=','wi.variant_id')
            ->where('wi.wishlist_id',$wishlist->id)
            ->orderByDesc('wi.added_at')->orderByDesc('wi.id')
            ->select('wi.id','wi.product_id','wi.variant_id','wi.price_snapshot','wi.price_seen_at','wi.added_at','p.name','p.slug','p.media','p.badge','v.sku','v.price','v.mrp')
            ->get();
        return ['items'=>$items->map(fn($r)=>$this->item($r))->all(),'count'=>$items->count(),'session_id'=>$sessionId];
    }

    public function add(int $productId, ?int $variantId, ?int $customerId, ?string $sessionId): array
    {
        $sessionId = $customerId ? null : $this->session($sessionId);
        $wishlist = $this->wishlist($customerId, $sessionId, true);
        $variant = $variantId ? DB::connection('priyasa')->table('priyasa_product_variants')->where('id',$variantId)->where('product_id',$productId)->first() : null;
        if ($variantId && !$variant) abort(422,'Variant does not belong to this product.');
        $product = DB::connection('priyasa')->table('priyasa_products')->where('id',$productId)->first();
        abort_unless($product,404);
        $price = $variant ? (float)($variant->price ?? 0) : null;
        $existing = DB::connection('priyasa')->table('priyasa_wishlist_items')->where('wishlist_id',$wishlist->id)->where('product_id',$productId)->where(function($q) use ($variantId){ $variantId === null ? $q->whereNull('variant_id') : $q->where('variant_id',$variantId); })->first();
        if (!$existing) {
            DB::connection('priyasa')->table('priyasa_wishlist_items')->insert(['wishlist_id'=>$wishlist->id,'product_id'=>$productId,'variant_id'=>$variantId,'price_snapshot'=>$price,'price_seen_at'=>now(),'added_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        } else {
            DB::connection('priyasa')->table('priyasa_wishlist_items')->where('id',$existing->id)->update(['price_snapshot'=>$price,'price_seen_at'=>now(),'updated_at'=>now()]);
        }
        return $this->list($customerId,$sessionId);
    }

    public function remove(int $itemId, ?int $customerId, ?string $sessionId): array
    {
        $wishlist = $this->wishlist($customerId, $customerId ? null : $sessionId, false);
        abort_unless($wishlist,404);
        $deleted = DB::connection('priyasa')->table('priyasa_wishlist_items')->where('id',$itemId)->where('wishlist_id',$wishlist->id)->delete();
        abort_unless($deleted,404);
        return $this->list($customerId,$sessionId);
    }

    public function merge(?int $customerId, ?string $sessionId): array
    {
        abort_unless($customerId,401);
        $guest = $this->wishlist(null,$sessionId,false);
        $customer = $this->wishlist($customerId,null,true);
        if ($guest) {
            $rows = DB::connection('priyasa')->table('priyasa_wishlist_items')->where('wishlist_id',$guest->id)->get();
            foreach ($rows as $row) {
                $exists = DB::connection('priyasa')->table('priyasa_wishlist_items')->where('wishlist_id',$customer->id)->where('product_id',$row->product_id)->where(function($q) use ($row){ $row->variant_id === null ? $q->whereNull('variant_id') : $q->where('variant_id',$row->variant_id); })->exists();
                if (!$exists) DB::connection('priyasa')->table('priyasa_wishlist_items')->where('id',$row->id)->update(['wishlist_id'=>$customer->id,'updated_at'=>now()]);
                else DB::connection('priyasa')->table('priyasa_wishlist_items')->where('id',$row->id)->delete();
            }
            DB::connection('priyasa')->table('priyasa_wishlists')->where('id',$guest->id)->delete();
        }
        return $this->list($customerId,null);
    }

    public function setAlert(int $productId, string $type, bool $enabled, ?int $customerId, ?string $sessionId): array
    {
        abort_unless(in_array($type,['price_drop','back_in_stock'],true),422,'Unsupported alert type.');
        $sessionId = $customerId ? null : $this->session($sessionId);
        $base = ['product_id'=>$productId,'type'=>$type];
        $where = $customerId ? ['customer_id'=>$customerId] : ['session_id'=>$sessionId];
        $exists = DB::connection('priyasa')->table('priyasa_engagement_alerts')->where($base)->where($where)->first();
        if ($exists) DB::connection('priyasa')->table('priyasa_engagement_alerts')->where('id',$exists->id)->update(['enabled'=>$enabled,'updated_at'=>now()]);
        else DB::connection('priyasa')->table('priyasa_engagement_alerts')->insert(array_merge($base,$where,['enabled'=>$enabled,'created_at'=>now(),'updated_at'=>now()]));
        return ['product_id'=>$productId,'type'=>$type,'enabled'=>$enabled];
    }

    private function wishlist(?int $customerId, ?string $sessionId, bool $create): ?object
    {
        $q = DB::connection('priyasa')->table('priyasa_wishlists');
        $row = $customerId ? $q->where('customer_id',$customerId)->first() : ($sessionId ? $q->where('session_id',$sessionId)->first() : null);
        if ($row || !$create) return $row;
        $id = DB::connection('priyasa')->table('priyasa_wishlists')->insertGetId(['customer_id'=>$customerId,'session_id'=>$sessionId,'created_at'=>now(),'updated_at'=>now()]);
        return DB::connection('priyasa')->table('priyasa_wishlists')->where('id',$id)->first();
    }

    private function session(?string $sessionId): string
    {
        return $sessionId && preg_match('/^[A-Za-z0-9._:-]{8,120}$/',$sessionId) ? $sessionId : (string)Str::uuid();
    }

    private function item(object $r): array
    {
        $price=(float)($r->price ?? 0); $snapshot=$r->price_snapshot===null?null:(float)$r->price_snapshot;
        return ['id'=>$r->id,'product_id'=>$r->product_id,'variant_id'=>$r->variant_id,'name'=>$r->name,'slug'=>$r->slug,'sku'=>$r->sku,'price'=>$price,'mrp'=>$r->mrp===null?null:(float)$r->mrp,'price_snapshot'=>$snapshot,'price_changed'=>$snapshot!==null && abs($price-$snapshot)>0.005,'badge'=>$r->badge,'media'=>$this->media($r->media),'added_at'=>$r->added_at];
    }
    private function media($value): array { if(is_array($value)) return $value; if(is_string($value)){ $x=json_decode($value,true); return is_array($x)?$x:[]; } return []; }
}
