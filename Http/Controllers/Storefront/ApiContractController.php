<?php
namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Services\StorefrontApiContract;

final class ApiContractController extends Controller
{
    public function bootstrap(Request $request, StorefrontApiContract $contract)
    {
        $user = $request->user();
        $customer = $user?->customer;
        return response()->json($contract->success('storefront.bootstrap', [
            'authenticated'=>(bool)$user,
            'customer'=>$customer ? ['id'=>$customer->id] : null,
            'capabilities'=>[
                'cart'=>true,'checkout'=>true,'orders'=>true,'wishlist'=>true,
                'recommendations'=>true,'notifications'=>true,'whatsapp'=>true,
            ],
            'api'=>['version'=>'v1','contract'=>'1.0'],
        ]));
    }

    public function me(Request $request, StorefrontApiContract $contract)
    {
        $user = $request->user();
        if (!$user) return response()->json($contract->error('AUTH_REQUIRED','Authentication required.',[],401),401);
        $customer = $user->customer;
        return response()->json($contract->success('account.me', [
            'user'=>['id'=>$user->id,'name'=>$user->name ?? null,'phone'=>$user->phone ?? $user->mobile ?? null,'email'=>$user->email ?? null],
            'customer'=>$customer ? ['id'=>$customer->id] : null,
        ]));
    }
}
