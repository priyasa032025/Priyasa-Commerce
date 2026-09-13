<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\PriyasaCore\Models\Customer;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = Customer::query()->withCount('orders')->latest('created_at');
        if ($search = trim((string) $request->query('search'))) {
            $q->where(function ($x) use ($search) {
                $x->where('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }
        if ($status = $request->query('status')) $q->where('status', $status);
        return response()->json(['success' => true, 'data' => $q->paginate(min((int) $request->query('per_page', 25), 100))]);
    }

    public function show(Customer $customer)
    {
        $customer->load('addresses');
        $orders = $customer->orders()->with(['items', 'payment', 'shipment'])->latest()->paginate(20);
        return response()->json(['success' => true, 'data' => [
            'customer' => $customer,
            'addresses' => $customer->addresses,
            'orders' => $orders,
        ]]);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'marketing_opt_in' => 'sometimes|boolean',
            'status' => ['sometimes', Rule::in(['active', 'blocked'])],
        ]);
        $customer->fill($data)->save();
        return response()->json(['success' => true, 'data' => $customer->fresh()->load('addresses')]);
    }

    public function status(Request $request, Customer $customer)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'blocked'])]]);
        $customer->update(['status' => $data['status']]);
        return response()->json(['success' => true, 'data' => $customer->fresh()]);
    }
}
