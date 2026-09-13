<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Services\SearchDiscoveryService;
use Modules\PriyasaCore\Services\StorefrontApiContract;

final class SearchDiscoveryController extends Controller
{
    public function __construct(private readonly SearchDiscoveryService $service, private readonly StorefrontApiContract $contract) {}

    public function search(Request $request)
    {
        return $this->contract->success('search.results',$this->service->search($request->all()));
    }
    public function suggestions(Request $request)
    {
        return $this->contract->success('search.suggestions',['items'=>$this->service->suggestions((string)$request->query('q',''),(int)$request->query('limit',8))]);
    }
    public function trending(Request $request)
    {
        return $this->contract->success('search.trending',['items'=>$this->service->trending((int)$request->query('limit',10))]);
    }
}
