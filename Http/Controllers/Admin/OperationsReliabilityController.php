<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Modules\PriyasaCore\Jobs\ExpireInventoryReservations;
use Modules\PriyasaCore\Models\InventoryReservation;

final class OperationsReliabilityController extends Controller
{
    public function inventoryReservations(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'type' => 'operations.inventory_reservations',
            'data' => InventoryReservation::query()
                ->with(['variant:id,sku', 'order:id,order_number'])
                ->where('status', 'active')
                ->orderBy('expires_at')
                ->paginate(50),
            'meta' => [], 'errors' => [], 'api_version' => 'v1',
        ]);
    }

    public function expireReservations(): JsonResponse
    {
        $result = Bus::dispatchSync(new ExpireInventoryReservations());
        return response()->json([
            'success' => true,
            'type' => 'operations.inventory_reservations.expired',
            'data' => ['result' => $result],
            'meta' => [], 'errors' => [], 'api_version' => 'v1',
        ], 200);
    }
}
