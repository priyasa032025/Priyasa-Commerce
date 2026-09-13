<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Services\PersonalizedDiscoveryService;
use Modules\PriyasaCore\Services\StorefrontApiContract;

final class PersonalizedDiscoveryController extends Controller
{
    public function __construct(private readonly PersonalizedDiscoveryService $service, private readonly StorefrontApiContract $contract) {}

    public function home(Request $request)
    {
        $user=$request->user(); $customerId=$user?->customer?->id;
        return $this->contract->success('recommendations.home',$this->service->home($customerId,$request->header('X-Session-Id'),(int)$request->query('limit',12)));
    }
    public function recommendations(Request $request)
    {
        $user=$request->user(); $customerId=$user?->customer?->id;
        return $this->contract->success('recommendations.results',$this->service->recommendations($customerId,$request->header('X-Session-Id'),$request->integer('product_id') ?: null,(int)$request->query('limit',12)));
    }
}
