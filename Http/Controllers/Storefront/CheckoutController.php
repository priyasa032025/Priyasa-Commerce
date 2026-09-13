<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Services\CheckoutService;
use Modules\PriyasaCore\Services\CustomerResolver;

final class CheckoutController extends Controller
{
    private function customer(Request $request): Customer
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated.');
        return app(CustomerResolver::class)->resolve($user);
    }

    public function validateCart(Request $request, CheckoutService $checkout)
    {
        $data = $request->validate(['coupon_code' => 'nullable|string|max:64']);
        return response()->json([
            'success' => true,
            'data' => $checkout->quote($this->customer($request), $data['coupon_code'] ?? null),
        ]);
    }

    public function createOrder(Request $request, CheckoutService $checkout)
    {
        $data = $request->validate([
            'shipping_address_id' => 'required|integer',
            'coupon_code' => 'nullable|string|max:64',
            'payment_method' => ['required', 'string', 'max:32', 'in:razorpay,cod'],
        ]);

        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key !== '' && strlen($key) > 191) {
            abort(422, 'Idempotency-Key must be 191 characters or fewer.');
        }

        $order = $checkout->place(
            $this->customer($request),
            (int) $data['shipping_address_id'],
            strtolower((string) $data['payment_method']),
            $data['coupon_code'] ?? null,
            'website',
            $key !== '' ? $key : null
        );

        return response()->json([
            'success' => true,
            'data' => $order,
            'meta' => ['idempotent' => $key !== ''],
        ], 201);
    }
}
