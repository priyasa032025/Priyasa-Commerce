<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\AuditLog;
use Modules\PriyasaCore\Models\SystemSetting;

final class SettingsController extends Controller
{
    /** Only operational, non-secret settings belong in this API. Secrets remain environment/config managed. */
    private const DEFINITIONS = [
        'store.name' => ['label'=>'Store name','type'=>'string','group'=>'Store'],
        'store.currency' => ['label'=>'Currency','type'=>'string','group'=>'Store'],
        'store.timezone' => ['label'=>'Timezone','type'=>'string','group'=>'Store'],
        'store.maintenance_mode' => ['label'=>'Maintenance mode','type'=>'boolean','group'=>'Store'],
        'checkout.cod_enabled' => ['label'=>'Cash on delivery','type'=>'boolean','group'=>'Checkout'],
        'checkout.minimum_order_value' => ['label'=>'Minimum order value','type'=>'number','group'=>'Checkout'],
        'notifications.order_email_enabled' => ['label'=>'Order email notifications','type'=>'boolean','group'=>'Notifications'],
        'notifications.order_whatsapp_enabled' => ['label'=>'Order WhatsApp notifications','type'=>'boolean','group'=>'Notifications'],
        'shipping.free_shipping_threshold' => ['label'=>'Free shipping threshold','type'=>'number','group'=>'Shipping'],
    ];

    public function index(): JsonResponse
    {
        $rows = SystemSetting::query()->whereIn('key', array_keys(self::DEFINITIONS))->get()->keyBy('key');
        $settings = collect(self::DEFINITIONS)->map(function (array $definition, string $key) use ($rows) {
            $row = $rows->get($key);
            return [
                'key' => $key,
                ...$definition,
                'value' => $row ? $this->decode($row->value, $definition['type']) : null,
                'configured' => (bool) $row,
                'updated_at' => $row?->updated_at?->toISOString(),
            ];
        })->values();

        return response()->json(['success'=>true,'data'=>['settings'=>$settings]]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(['settings'=>'required|array|min:1']);
        $input = $validated['settings'];
        $unknown = array_diff(array_keys($input), array_keys(self::DEFINITIONS));
        if ($unknown) return response()->json(['success'=>false,'message'=>'Unsupported setting key.','errors'=>['settings'=>$unknown]], 422);

        $actor = $request->user();
        DB::connection('priyasa')->transaction(function () use ($input, $request, $actor): void {
            foreach ($input as $key => $value) {
                $definition = self::DEFINITIONS[$key];
                $normalized = $this->normalize($value, $definition['type']);
                SystemSetting::updateOrCreate(
                    ['key'=>$key],
                    ['value'=>$this->encode($normalized, $definition['type']), 'type'=>$definition['type'], 'is_public'=>false]
                );
                AuditLog::create([
                    'action'=>'settings.updated', 'actor_type'=>$actor ? get_class($actor) : null,
                    'actor_id'=>$actor?->getAuthIdentifier(), 'entity_type'=>'system_setting', 'entity_id'=>$key,
                    'data'=>['key'=>$key,'value'=>$normalized], 'ip'=>$request->ip(),
                    'request_id'=>$request->header('X-Request-ID'),
                ]);
            }
        });

        return $this->index();
    }

    private function normalize(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? throw new \InvalidArgumentException('Invalid boolean setting value.'),
            'number' => is_numeric($value) && (float)$value >= 0 ? (float)$value : throw new \InvalidArgumentException('Invalid numeric setting value.'),
            default => is_scalar($value) ? trim((string)$value) : throw new \InvalidArgumentException('Invalid setting value.'),
        };
    }

    private function encode(mixed $value, string $type): string
    {
        return $type === 'boolean' ? ($value ? '1' : '0') : (string)$value;
    }

    private function decode(?string $value, string $type): mixed
    {
        if ($value === null) return null;
        return match ($type) {
            'boolean' => $value === '1' || strtolower($value) === 'true',
            'number' => (float)$value,
            default => $value,
        };
    }
}
