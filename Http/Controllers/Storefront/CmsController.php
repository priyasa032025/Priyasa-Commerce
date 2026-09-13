<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Modules\PriyasaCore\Models\CmsSection;

final class CmsController extends Controller
{
    public function home()
    {
        $rows=CmsSection::query()->published()->orderBy('sort_order')->orderBy('id')->get()->keyBy('key');
        $legacy=['home-offer'=>'home-offer-banner','home-categories'=>'home-editorial','home-trending'=>'home-best-sellers'];
        foreach($legacy as $old=>$new){if(!$rows->has($new)&&$rows->has($old))$rows->put($new,$rows->get($old));}
        $sections=[];
        foreach($this->contractSections() as $preset){
            $row=$rows->get($preset['key']);
            $content=is_array($row?->content)?$row->content:[];
            $merged=array_replace_recursive($preset['content'],$content);
            if($row){
                if($row->image_url)$merged['image_url']=$row->image_url;
                if($row->cta_label!==null||$row->cta_href!==null){$merged['cta']=$merged['cta']??[];$merged['cta']['label']=$row->cta_label??data_get($merged,'cta.label','Shop Now');$merged['cta']['href']=$row->cta_href??data_get($merged,'cta.href','/shop');}
            }
            $isActive=$row ? $this->isPublished($row) : true;
            $sections[]=['id'=>(int)($row?->id ?: $preset['id']),'key'=>$preset['key'],'type'=>$preset['type'],'sort_order'=>$preset['sort_order'],'is_active'=>$isActive,'title'=>$row?->title ?? $preset['title'],'subtitle'=>$row?->subtitle ?? $preset['subtitle'],'content'=>$merged];
        }
                $timestamps=$rows->filter(fn($r)=>$this->isPublished($r))->pluck('updated_at');
        $version=$timestamps->isEmpty()?0:(int)$timestamps->max()->getTimestamp();
        return response()->json(['success'=>true,'data'=>['version'=>$version,'page'=>'home','layout'=>'myntra-fashion-v1','currency'=>'INR','locale'=>'en-IN','header'=>['logo'=>['image_url'=>config('priyasacore.storefront.logo_url','https://cdn.priyasa.com/brand/priyasa-logo.svg'),'alt'=>'PRIYASA'],'search'=>['enabled'=>true,'placeholder'=>'Search for products, brands and more...','endpoint'=>'/api/v1/storefront/search','suggestions_endpoint'=>'/api/v1/storefront/search/suggestions','trending'=>['Kurtis','Kurta Sets','Anarkali','Festive Wear']],'cart'=>['enabled'=>true,'show_count'=>true]],'sections'=>$sections]]);
    }

    public function show(string $key){$section=CmsSection::query()->published()->where('key',$key)->firstOrFail();return response()->json(['success'=>true,'data'=>$section]);}

    private function isPublished(CmsSection $section): bool
    {
        $now=now();
        return (bool)$section->is_active && (!$section->starts_at || $section->starts_at->lte($now)) && (!$section->ends_at || $section->ends_at->gte($now));
    }

    private function contractSections(): array
    {
        return [
            ['id'=>1,'key'=>'home-hero','type'=>'hero_slider','sort_order'=>10,'title'=>null,'subtitle'=>null,'content'=>['autoplay'=>true,'interval_ms'=>4500,'items'=>[]]],
            ['id'=>2,'key'=>'home-promises','type'=>'service_strip','sort_order'=>20,'title'=>null,'subtitle'=>null,'content'=>['items'=>[]]],
            ['id'=>3,'key'=>'home-new-arrivals','type'=>'product_carousel','sort_order'=>30,'title'=>'New Arrivals','subtitle'=>'Fresh styles just landed','content'=>['query'=>['sort'=>'newest','limit'=>12,'in_stock'=>true],'display'=>['desktop_columns'=>4,'tablet_columns'=>3,'mobile_cards'=>2,'mobile_scroll'=>true],'cta'=>['label'=>'View All','href'=>'/shop?sort=newest']]],
            ['id'=>4,'key'=>'home-editorial','type'=>'editorial_grid','sort_order'=>40,'title'=>'Shop The Edit','subtitle'=>null,'content'=>['columns'=>3,'items'=>[]]],
            ['id'=>5,'key'=>'home-flash-sale','type'=>'flash_sale','sort_order'=>50,'title'=>'Flash Sale','subtitle'=>'Limited time. Limited styles.','content'=>['query'=>['sort'=>'discount','limit'=>12,'in_stock'=>true,'sale_only'=>true],'sale'=>['starts_at'=>null,'ends_at'=>null,'show_countdown'=>true],'display'=>['mobile_scroll'=>true,'show_discount'=>true,'show_mrp'=>true,'show_stock_progress'=>true],'cta'=>['label'=>'View All','href'=>'/shop?sort=discount']]],
            ['id'=>6,'key'=>'home-offer-banner','type'=>'offer_banner','sort_order'=>60,'title'=>null,'subtitle'=>null,'content'=>['eyebrow'=>'LIMITED TIME OFFER','title'=>'FLAT 30% OFF','subtitle'=>'Upgrade your wardrobe with selected styles.','image_url'=>null,'mobile_image_url'=>null,'cta'=>['label'=>'Shop Sale','href'=>'/shop?sort=discount']]],
            ['id'=>7,'key'=>'home-best-sellers','type'=>'product_carousel','sort_order'=>70,'title'=>'Best Sellers','subtitle'=>'Loved by Priyasa shoppers','content'=>['query'=>['sort'=>'popular','limit'=>12,'in_stock'=>true],'display'=>['desktop_columns'=>4,'tablet_columns'=>3,'mobile_cards'=>2,'mobile_scroll'=>true],'cta'=>['label'=>'View All','href'=>'/shop?sort=popular']]],
            ['id'=>8,'key'=>'home-all-products','type'=>'product_grid','sort_order'=>80,'title'=>'More To Explore','subtitle'=>'Discover your next favourite','content'=>['query'=>['sort'=>'newest','limit'=>30,'in_stock'=>true],'display'=>['desktop_columns'=>5,'tablet_columns'=>4,'mobile_columns'=>2,'load_more'=>true,'load_more_step'=>12],'cta'=>['label'=>'Explore All','href'=>'/shop']]],
            ['id'=>9,'key'=>'home-review-carousel','type'=>'review_carousel','sort_order'=>90,'title'=>'Loved By You','subtitle'=>'Real reviews from Priyasa customers','content'=>['source'=>'product_reviews','limit'=>10,'display'=>['show_rating'=>true,'show_customer'=>true,'show_product'=>true,'show_product_image'=>true,'mobile_scroll'=>true]]],
            ['id'=>10,'key'=>'home-fashion-banner','type'=>'image_banner','sort_order'=>100,'title'=>'Style That Speaks','subtitle'=>'Premium fashion curated for your everyday.','content'=>['image_url'=>null,'mobile_image_url'=>null,'cta'=>['label'=>'Discover Collection','href'=>'/shop']]],
            ['id'=>11,'key'=>'home-recommendations','type'=>'personalized_products','sort_order'=>110,'title'=>'Recommended For You','subtitle'=>null,'content'=>['query'=>['strategy'=>'personalized','limit'=>12,'fallback_sort'=>'popular']]],
        ];
    }
}
