<?php
namespace Modules\PriyasaCore\Http\Controllers\Storefront;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Http\Controllers\Controller;
use Modules\PriyasaCore\Services\{CustomerResolver,CustomerGrowthService};
final class CustomerGrowthController extends Controller { private function c(Request $r){return app(CustomerResolver::class)->resolve($r);} public function account(Request $r){return response()->json(app(CustomerGrowthService::class)->account($this->c($r)));} public function referralCode(Request $r){return response()->json(['referral_code'=>app(CustomerGrowthService::class)->referralCode($this->c($r))]);} public function wallet(Request $r){$c=$this->c($r);return response()->json(app(CustomerGrowthService::class)->account($c)['wallet']);} public function loyalty(Request $r){$c=$this->c($r);return response()->json(app(CustomerGrowthService::class)->account($c)['loyalty']);} }
