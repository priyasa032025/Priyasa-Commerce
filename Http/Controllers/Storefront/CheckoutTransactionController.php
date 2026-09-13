<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Services\CheckoutTransactionService;
use Modules\PriyasaCore\Services\CustomerResolver;

final class CheckoutTransactionController extends Controller
{
    private function customer(Request $request): Customer
    {
        abort_unless($request->user(), 401, 'Unauthenticated.');
        return app(CustomerResolver::class)->resolve($request->user());
    }

    public function quote(Request $request, CheckoutTransactionService $service)
    {
        $data = $request->validate([
            'shipping_address_id' => 'required|integer',
            'coupon_code' => 'nullable|string|max:64',
            'payment_method' => ['nullable', 'string', 'in:razorpay,cod'],
        ]);
        $method = strtolower((string) ($data['payment_method'] ?? 'razorpay'));
        $quote = $service->quote($this->customer($request), (int) $data['shipping_address_id'], $data['coupon_code'] ?? null, $method !== 'cod');
        return response()->json(['success' => true, 'data' => $quote, 'meta' => ['authoritative' => true]]);
    }

    public function place(Request $request, CheckoutTransactionService $service)
    {
        $data = $request->validate([
            'shipping_address_id' => 'required|integer',
            'coupon_code' => 'nullable|string|max:64',
            'payment_method' => ['required', 'string', 'in:razorpay,cod'],
        ]);
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key !== '' && strlen($key) > 191) abort(422, 'Idempotency-Key must be 191 characters or fewer.');
        $order = $service->place($this->customer($request), (int) $data['shipping_address_id'], strtolower($data['payment_method']), $data['coupon_code'] ?? null, 'website', $key !== '' ? $key : null);
        return response()->json(['success' => true, 'data' => $order, 'meta' => ['authoritative' => true, 'idempotent' => $key !== '']], 201);
    }
}
