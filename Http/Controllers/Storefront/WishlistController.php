<?php
namespace Modules\PriyasaCore\Http\Controllers\Storefront;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Services\WishlistService;
use Modules\PriyasaCore\Services\CustomerResolver;
class WishlistController extends Controller { private function customer(Request $r):Customer{return app(CustomerResolver::class)->resolve($r->user());} public function index(Request $r,WishlistService $s){return response()->json(['success'=>true,'data'=>$s->list($this->customer($r))]);} public function toggle(Request $r,int $variant,WishlistService $s){return response()->json(['success'=>true,'data'=>$s->toggle($this->customer($r),$variant)]);} }
