<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\PriyasaCore\Models\Customer;
use RuntimeException;

final class CheckoutExperienceService
{
    public function addresses(Customer $customer): array
    {
        $relation = $customer->addresses();
        $items = $relation->get();
        return [
            'items' => $items->map(fn (Model $a) => $this->address($a))->values()->all(),
            'count' => $items->count(),
        ];
    }

    public function createAddress(Customer $customer, array $data): array
    {
        $relation = $customer->addresses();
        $model = $relation->getRelated();
        $columns = $this->columns($model);
        $payload = $this->mapAddressPayload($data, $columns);
        $payload['customer_id'] ??= $customer->id;
        if (array_key_exists('is_default', $columns) && !empty($data['is_default'])) {
            $relation->update(['is_default' => false]);
        }
        /** @var Model $address */
        $address = $relation->create($payload);
        if (array_key_exists('is_default', $columns) && $relation->whereKey($address->getKey())->value('is_default') === null) {
            // Legacy schemas without a default flag are intentionally left unchanged.
        }
        return $this->address($address->fresh());
    }

    public function updateAddress(Customer $customer, int $id, array $data): array
    {
        $relation = $customer->addresses();
        /** @var Model|null $address */
        $address = $relation->whereKey($id)->first();
        if (!$address) throw new RuntimeException('Address not found.');
        $columns = $this->columns($address);
        $payload = $this->mapAddressPayload($data, $columns);
        if (array_key_exists('is_default', $columns) && !empty($data['is_default'])) {
            $relation->whereKey($id)->update(['is_default' => false]);
        }
        if ($payload !== []) $address->forceFill($payload)->save();
        return $this->address($address->fresh());
    }

    public function deleteAddress(Customer $customer, int $id): void
    {
        $address = $customer->addresses()->whereKey($id)->first();
        if (!$address) throw new RuntimeException('Address not found.');
        $address->delete();
    }

    public function setDefaultAddress(Customer $customer, int $id): array
    {
        $relation = $customer->addresses();
        $address = $relation->whereKey($id)->first();
        if (!$address) throw new RuntimeException('Address not found.');
        $columns = $this->columns($address);
        if (!array_key_exists('is_default', $columns)) {
            throw new RuntimeException('Default address is not supported by the installed address schema.');
        }
        $relation->update(['is_default' => false]);
        $address->forceFill(['is_default' => true])->save();
        return $this->address($address->fresh());
    }

    public function delivery(Customer $customer, int $addressId, float $orderValue = 0.0): array
    {
        $address = $customer->addresses()->whereKey($addressId)->first();
        if (!$address) throw new RuntimeException('Shipping address does not belong to this customer.');
        $pincode = $this->pincode($address);
        if ($pincode === '') throw new RuntimeException('A valid delivery pincode is required.');
        if (!preg_match('/^[0-9]{6}$/', $pincode)) throw new RuntimeException('Invalid Indian pincode.');

        $cfg = (array) config('priyasacore.checkout_delivery', config('p40_checkout_delivery', []));
        $blocked = array_map('strval', (array) ($cfg['blocked_pincodes'] ?? []));
        $allow = array_map('strval', (array) ($cfg['serviceable_pincodes'] ?? []));
        $serviceable = !in_array($pincode, $blocked, true) && ($allow === [] || in_array($pincode, $allow, true));
        $freeAbove = isset($cfg['free_shipping_above']) ? (float) $cfg['free_shipping_above'] : null;
        $charge = (float) ($cfg['shipping_charge'] ?? 0);
        if ($serviceable && $freeAbove !== null && $orderValue >= $freeAbove) $charge = 0.0;

        $codMin = (float) ($cfg['cod_min_order'] ?? 0);
        $codMax = isset($cfg['cod_max_order']) ? (float) $cfg['cod_max_order'] : null;
        $codEnabled = (bool) ($cfg['cod_enabled'] ?? true);
        $codEligible = $serviceable && $codEnabled && $orderValue >= $codMin && ($codMax === null || $orderValue <= $codMax);
        $eta = $serviceable ? ($cfg['default_eta'] ?? '3-7 business days') : null;

        return [
            'pincode' => $pincode,
            'serviceable' => $serviceable,
            'shipping_charge' => round($charge, 2),
            'cod' => ['eligible' => $codEligible, 'reason' => $codEligible ? null : 'COD is unavailable for this delivery.'],
            'delivery_promise' => $eta,
            'source' => 'configuration',
        ];
    }

    private function address(Model $address): array
    {
        $a = $address->toArray();
        $pick = static function (array $a, array $keys, $default = null) {
            foreach ($keys as $key) if (array_key_exists($key, $a) && $a[$key] !== null) return $a[$key];
            return $default;
        };
        return [
            'id' => $address->getKey(),
            'name' => $pick($a, ['name','full_name','recipient_name']),
            'phone' => $pick($a, ['phone','mobile','contact_number']),
            'address_line1' => $pick($a, ['address_line1','line1','address']),
            'address_line2' => $pick($a, ['address_line2','line2']),
            'landmark' => $pick($a, ['landmark']),
            'city' => $pick($a, ['city']),
            'state' => $pick($a, ['state','state_name']),
            'pincode' => $pick($a, ['pincode','postal_code','zip']),
            'country' => $pick($a, ['country'], 'India'),
            'is_default' => (bool) $pick($a, ['is_default','default'], false),
        ];
    }

    private function pincode(Model $address): string
    {
        $a = $address->toArray();
        foreach (['pincode','postal_code','zip'] as $key) if (!empty($a[$key])) return trim((string) $a[$key]);
        return '';
    }

    private function columns(Model $model): array
    {
        return array_flip(Schema::connection('priyasa')->getColumnListing($model->getTable()));
    }

    private function mapAddressPayload(array $data, array $columns): array
    {
        $aliases = [
            'name' => ['name','full_name','recipient_name'], 'phone' => ['phone','mobile','contact_number'],
            'address_line1' => ['address_line1','line1','address'], 'address_line2' => ['address_line2','line2'],
            'landmark' => ['landmark'], 'city' => ['city'], 'state' => ['state','state_name'],
            'pincode' => ['pincode','postal_code','zip'], 'country' => ['country'], 'is_default' => ['is_default','default'],
        ];
        $out = [];
        foreach ($aliases as $canonical => $names) {
            if (!array_key_exists($canonical, $data)) continue;
            foreach ($names as $column) if (array_key_exists($column, $columns)) { $out[$column] = $data[$canonical]; break; }
        }
        return $out;
    }
}
