<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Services\WishlistEngagementService;

final class WishlistEngagementController extends Controller
{
    public function index(Request $request): array { $c=$request->user()?->customer; return app(WishlistEngagementService::class)->list($c?->getKey(),$request->header('X-Wishlist-Token')); }
    public function add(Request $request): array { $d=$request->validate(['product_id'=>['required','integer','min:1'],'variant_id'=>['nullable','integer','min:1']]); $c=$request->user()?->customer; return app(WishlistEngagementService::class)->add((int)$d['product_id'],isset($d['variant_id'])?(int)$d['variant_id']:null,$c?->getKey(),$request->header('X-Wishlist-Token')); }
    public function remove(Request $request,int $item): array { $c=$request->user()?->customer; return app(WishlistEngagementService::class)->remove($item,$c?->getKey(),$request->header('X-Wishlist-Token')); }
    public function merge(Request $request): array { $c=$request->user()?->customer; abort_unless($c,401); return app(WishlistEngagementService::class)->merge($c->getKey(),$request->header('X-Wishlist-Token')); }
    public function alert(Request $request,int $product): array { $d=$request->validate(['type'=>['required','string'],'enabled'=>['required','boolean']]); $c=$request->user()?->customer; return app(WishlistEngagementService::class)->setAlert($product,(string)$d['type'],(bool)$d['enabled'],$c?->getKey(),$request->header('X-Wishlist-Token')); }
}
