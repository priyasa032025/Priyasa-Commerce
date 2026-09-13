<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Services\CustomerResolver;

final class CustomerController extends Controller
{
    private function customer(Request $request): Customer
    {
        return app(CustomerResolver::class)->resolve($request->user());
    }

    public function me(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->customer($request)->loadCount('orders')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'marketing_opt_in' => 'sometimes|boolean',
        ]);
        $customer = $this->customer($request);
        $customer->update($data);
        return response()->json(['success' => true, 'data' => $customer->fresh()]);
    }
}
