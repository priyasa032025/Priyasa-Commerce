<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PriyasaCore\Models\Customer;
use RuntimeException;

final class CustomerAccountExperienceService
{
    public function me(Customer $customer): array
    {
        $customer->loadMissing('user');
        $data = $customer->toArray();
        $user = method_exists($customer, 'user') ? $customer->user : null;
        $userData = $user ? $user->toArray() : [];

        return [
            'id' => $customer->getKey(),
            'name' => $this->first($data, ['name','full_name']) ?? $this->first($userData, ['name']),
            'phone' => $this->first($data, ['phone','mobile']) ?? $this->first($userData, ['phone','mobile']),
            'email' => $this->first($data, ['email']) ?? $this->first($userData, ['email']),
            'avatar' => $this->first($data, ['avatar','avatar_url','profile_image']),
            'referral_code' => $this->first($data, ['referral_code']),
            'created_at' => $customer->created_at?->toISOString(),
            'verification' => [
                'phone' => $this->verification($data, ['phone_verified_at','mobile_verified_at']),
                'email' => $this->verification($data, ['email_verified_at']),
            ],
        ];
    }

    public function dashboard(Customer $customer): array
    {
        $orders = $this->ordersQuery($customer);
        $total = (clone $orders)->count();
        $active = (clone $orders)->whereIn('status', ['pending','pending_payment','confirmed','processing','packed','shipped','out_for_delivery'])->count();
        $delivered = (clone $orders)->where('status', 'delivered')->count();
        $cancelled = (clone $orders)->whereIn('status', ['cancelled','canceled'])->count();

        return [
            'profile' => $this->me($customer),
            'orders' => compact('total','active','delivered','cancelled'),
            'capabilities' => [
                'orders' => true,
                'addresses' => method_exists($customer, 'addresses'),
                'wishlist' => method_exists($customer, 'wishlist'),
                'wallet' => Schema::connection('priyasa')->hasTable('priyasa_wallets'),
                'loyalty' => Schema::connection('priyasa')->hasTable('priyasa_loyalty_accounts'),
                'notifications' => Schema::connection('priyasa')->hasTable('priyasa_notification_inbox'),
            ],
        ];
    }

    public function orders(Customer $customer, int $page = 1, int $perPage = 20): array
    {
        $perPage = max(1, min(50, $perPage));
        $query = $this->ordersQuery($customer);
        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map(fn ($row) => $this->orderSummary((array) $row))->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'has_more' => ($page * $perPage) < $total,
            ],
        ];
    }

    public function order(Customer $customer, int $orderId): array
    {
        $row = $this->ordersQuery($customer)->where('id', $orderId)->first();
        if (!$row) throw new RuntimeException('Order not found.');
        $order = (array) $row;
        $result = $this->orderSummary($order);
        $result['items'] = $this->items($orderId);
        $result['timeline'] = $this->timeline($orderId);
        $result['shipments'] = $this->shipments($orderId);
        return $result;
    }

    public function security(Customer $customer): array
    {
        $customer->loadMissing('user');
        $user = $customer->user;
        return [
            'current_session' => [
                'authenticated' => true,
                'user_id' => $user?->getKey(),
                'customer_id' => $customer->getKey(),
            ],
            'token_rotation_supported' => true,
            'logout_current_supported' => true,
            'logout_all_supported' => true,
        ];
    }

    private function ordersQuery(Customer $customer): Builder
    {
        $table = 'priyasa_orders';
        if (!Schema::connection('priyasa')->hasTable($table)) return DB::connection('priyasa')->query()->from($table)->whereRaw('1=0');
        return DB::connection('priyasa')->table($table)->where('customer_id', $customer->getKey())->orderByDesc('created_at')->orderByDesc('id');
    }

    private function orderSummary(array $o): array
    {
        return [
            'id' => $o['id'] ?? null,
            'order_number' => $this->first($o, ['order_number','number','order_no']),
            'status' => $o['status'] ?? null,
            'payment_status' => $o['payment_status'] ?? null,
            'payment_method' => $o['payment_method'] ?? null,
            'currency' => $o['currency'] ?? config('priyasacore.currency', 'INR'),
            'subtotal' => $this->number($o, ['subtotal']),
            'discount_total' => $this->number($o, ['discount_total']),
            'shipping_total' => $this->number($o, ['shipping_total']),
            'tax_total' => $this->number($o, ['tax_total','tax_amount']),
            'grand_total' => $this->number($o, ['grand_total']),
            'created_at' => $o['created_at'] ?? null,
            'delivered_at' => $o['delivered_at'] ?? null,
            'cancelled_at' => $o['cancelled_at'] ?? null,
        ];
    }

    private function items(int $orderId): array
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_order_items')) return [];
        $rows = DB::connection('priyasa')->table('priyasa_order_items')->where('order_id', $orderId)->orderBy('id')->get();
        return $rows->map(function ($r) {
            $x = (array) $r;
            return [
                'id' => $x['id'] ?? null,
                'variant_id' => $x['variant_id'] ?? null,
                'sku' => $x['sku'] ?? null,
                'product_name' => $x['product_name'] ?? null,
                'variant_label' => $x['variant_label'] ?? null,
                'quantity' => (int) ($x['quantity'] ?? 0),
                'unit_price' => $this->number($x, ['unit_price']),
                'tax_amount' => $this->number($x, ['tax_amount']),
                'line_total' => $this->number($x, ['line_total']),
            ];
        })->all();
    }

    private function timeline(int $orderId): array
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_order_status_history')) return [];
        $columns = array_flip(Schema::connection('priyasa')->getColumnListing('priyasa_order_status_history'));
        $q = DB::connection('priyasa')->table('priyasa_order_status_history')->where('order_id', $orderId)->orderBy('created_at');
        return $q->get()->map(function ($r) use ($columns) {
            $x = (array) $r;
            return [
                'from_status' => $this->first($x, ['from_status','status']),
                'to_status' => $this->first($x, ['to_status','status']),
                'message' => $this->first($x, ['note','message']),
                'created_at' => $x['created_at'] ?? null,
            ];
        })->all();
    }

    private function shipments(int $orderId): array
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_shipments')) return [];
        return DB::connection('priyasa')->table('priyasa_shipments')->where('order_id', $orderId)->orderBy('id')->get()->map(function ($r) {
            $x = (array) $r;
            return [
                'id' => $x['id'] ?? null,
                'status' => $x['status'] ?? null,
                'provider' => $x['courier_name'] ?? $x['provider'] ?? null,
                'tracking_number' => $x['awb'] ?? $x['tracking_number'] ?? $x['provider_shipment_id'] ?? null,
                'shipped_at' => $x['shipped_at'] ?? null,
                'delivered_at' => $x['delivered_at'] ?? null,
                'cancelled_at' => $x['cancelled_at'] ?? null,
            ];
        })->all();
    }

    private function first(array $data, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) if (array_key_exists($key, $data) && $data[$key] !== null) return $data[$key];
        return $default;
    }

    private function number(array $data, array $keys): ?float
    {
        $v = $this->first($data, $keys);
        return $v === null ? null : round((float) $v, 2);
    }

    private function verification(array $data, array $keys): bool
    {
        foreach ($keys as $key) if (!empty($data[$key])) return true;
        return false;
    }
}
