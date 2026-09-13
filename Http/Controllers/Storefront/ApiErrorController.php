<?php
namespace Modules\PriyasaCore\Http\Controllers\Storefront;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Services\StorefrontApiContract;

final class ApiErrorController extends Controller
{
    public function notFound(Request $request, StorefrontApiContract $contract)
    {
        return response()->json($contract->error('NOT_FOUND','The requested resource was not found.'),404);
    }
}
