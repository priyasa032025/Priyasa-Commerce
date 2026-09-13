<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Contracts\ShippingProvider;
use Modules\PriyasaCore\Integrations\Shipping\ShiprocketProvider;
use Throwable;

final class ShippingController extends Controller
{
    public function serviceability(Request $request)
    {
        $data = $request->validate([
            'pincode' => 'required|string|regex:/^[0-9A-Za-z -]{3,16}$/',
            'payment_method' => 'nullable|string|in:cod,razorpay',
        ]);

        try {
            $provider = app(ShiprocketProvider::class);
            $result = $provider->serviceability(trim($data['pincode']), ($data['payment_method'] ?? 'razorpay') === 'cod' ? 'cod' : 'prepaid');
            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Shipping serviceability is temporarily unavailable.', 'data' => null], 503);
        }
    }
}
