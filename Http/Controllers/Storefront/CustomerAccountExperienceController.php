<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Services\CustomerAccountExperienceService;
use Modules\PriyasaCore\Services\CustomerResolver;

final class CustomerAccountExperienceController extends Controller
{
    private function customer(Request $request): Customer
    {
        abort_unless($request->user(), 401, 'Unauthenticated.');
        return app(CustomerResolver::class)->resolve($request->user());
    }

    public function me(Request $request, CustomerAccountExperienceService $service)
    { return response()->json(['success'=>true,'type'=>'account.me','data'=>$service->me($this->customer($request))]); }

    public function dashboard(Request $request, CustomerAccountExperienceService $service)
    { return response()->json(['success'=>true,'type'=>'account.dashboard','data'=>$service->dashboard($this->customer($request))]); }

    public function orders(Request $request, CustomerAccountExperienceService $service)
    {
        $data=$request->validate(['page'=>'nullable|integer|min:1','per_page'=>'nullable|integer|min:1|max:50']);
        return response()->json(['success'=>true,'type'=>'account.orders','data'=>$service->orders($this->customer($request),(int)($data['page']??1),(int)($data['per_page']??20))]);
    }

    public function order(Request $request, int $order, CustomerAccountExperienceService $service)
    { return response()->json(['success'=>true,'type'=>'account.order','data'=>$service->order($this->customer($request),$order)]); }

    public function security(Request $request, CustomerAccountExperienceService $service)
    { return response()->json(['success'=>true,'type'=>'account.security','data'=>$service->security($this->customer($request))]); }
}
