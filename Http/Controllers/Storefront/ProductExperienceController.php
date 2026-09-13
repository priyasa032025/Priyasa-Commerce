<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Models\Product;
use Modules\PriyasaCore\Services\ProductExperienceService;
use Modules\PriyasaCore\Services\StorefrontApiContract;

final class ProductExperienceController extends Controller
{
    public function show(Request $request, string $product, ProductExperienceService $service, StorefrontApiContract $contract)
    {
        $model = ctype_digit($product) ? Product::findOrFail((int)$product) : Product::where('slug',$product)->firstOrFail();
        return response()->json($contract->success('product.detail', $service->detail($model, $request->string('pincode')->toString() ?: null)));
    }

    public function availability(Request $request, int $variant, ProductExperienceService $service, StorefrontApiContract $contract)
    {
        return response()->json($contract->success('product.variant.availability', $service->availability($variant, $request->string('pincode')->toString() ?: null)));
    }
}
