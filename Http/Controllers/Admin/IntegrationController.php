<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * PriyasaCore-owned integration status API.
 *
 * Connector implementations remain in their owning modules. PriyasaCore only
 * exposes safe admin-facing status/storage APIs and never delegates to an
 * optional controller at runtime.
 */
final class IntegrationController extends Controller
{
    public function index(): mixed
    {
        $keys = ['meta', 'woocommerce', 'whatsapp', 'razorpay', 'shiprocket', 'fcm'];
        $stored = $this->storedIntegrations();
        $items = [];

        foreach ($keys as $key) {
            $row = $stored[$key] ?? null;
            $items[] = [
                'key' => $key,
                'name' => $row['name'] ?? Str::headline($key),
                'enabled' => (bool) ($row['enabled'] ?? false),
                'connected' => $this->connected($key, $row),
                'connected_at' => $row['connected_at'] ?? null,
                'last_tested_at' => $row['last_tested_at'] ?? null,
                'last_error' => $row['last_error'] ?? null,
                'credential_source' => $row['credential_source'] ?? 'server',
                'managed_by' => 'priyasa_core',
            ];
        }

        return response()->json(['data' => $items]);
    }

    public function connect(Request $request, string $key): mixed
    {
        $key = strtolower(trim($key));

        if ($key === 'woocommerce') return response()->json(['message'=>'WooCommerce credentials are server-side configuration. Use the WooCommerce status/sync endpoints.','code'=>'WOOCOMMERCE_SERVER_CONFIGURED']);

        if (!in_array($key, ['meta', 'whatsapp', 'razorpay', 'shiprocket', 'fcm'], true)) {
            return response()->json(['message' => 'Unsupported integration.', 'code' => 'UNSUPPORTED_INTEGRATION'], 422);
        }

        if (!Schema::connection('priyasa')->hasTable('commerce_integrations')) {
            return response()->json(['message' => 'Integration storage is not available.', 'code' => 'INTEGRATION_STORAGE_UNAVAILABLE'], 503);
        }

        $columns = Schema::connection('priyasa')->getColumnListing('commerce_integrations');
        $now = now();
        $values = [];
        if (in_array('key', $columns, true)) $values['key'] = $key;
        if (in_array('name', $columns, true)) $values['name'] = Str::headline($key);
        if (in_array('enabled', $columns, true)) $values['enabled'] = true;
        if (in_array('connected_at', $columns, true)) $values['connected_at'] = $now;
        if (in_array('updated_at', $columns, true)) $values['updated_at'] = $now;
        if (in_array('credential_source', $columns, true)) $values['credential_source'] = 'server';

        try {
            $query = DB::connection('priyasa')->table('commerce_integrations');
            $existing = in_array('key', $columns, true) ? $query->where('key', $key)->first() : null;
            if ($existing) {
                unset($values['key'], $values['name']);
                $query->where('id', $existing->id)->update($values);
            } else {
                if (!isset($values['key'])) return response()->json(['message' => 'Integration table is missing its key column.'], 503);
                if (in_array('created_at', $columns, true)) $values['created_at'] = $now;
                $query->insert($values);
            }
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Integration could not be saved.', 'code' => 'INTEGRATION_SAVE_FAILED'], 503);
        }

        return response()->json(['data' => ['key' => $key, 'connected' => true, 'credential_source' => 'server'], 'message' => 'Integration connected.']);
    }

    public function disconnect(string $key): mixed
    {
        $key = strtolower(trim($key));
        if ($key === 'woocommerce') return response()->json(['message'=>'WooCommerce credentials are server-side configuration; disable them in deployment configuration.','code'=>'WOOCOMMERCE_SERVER_CONFIGURED'],409);

        if (!Schema::connection('priyasa')->hasTable('commerce_integrations')) {
            return response()->json(['message' => 'Integration storage is not available.', 'code' => 'INTEGRATION_STORAGE_UNAVAILABLE'], 503);
        }

        $columns = Schema::connection('priyasa')->getColumnListing('commerce_integrations');
        $values = [];
        if (in_array('enabled', $columns, true)) $values['enabled'] = false;
        if (in_array('connected_at', $columns, true)) $values['connected_at'] = null;
        if (in_array('updated_at', $columns, true)) $values['updated_at'] = now();
        $updated = DB::connection('priyasa')->table('commerce_integrations')->where('key', $key)->update($values);

        return response()->json(['data' => ['key' => $key, 'connected' => false], 'message' => $updated ? 'Integration disconnected.' : 'Integration was already disconnected.']);
    }

    public function metaStatus(): mixed
    {
        $row = $this->storedIntegrations()['meta'] ?? null;
        $configured = (bool) (config('services.facebook.client_id') ?: env('META_APP_ID'));
        return response()->json(['data' => [
            'key' => 'meta',
            'configured' => $configured,
            'connected' => $this->connected('meta', $row),
            'credential_source' => 'server',
            'oauth_available' => $configured,
        ]]);
    }

    public function metaConnect(): mixed
    {
        $configured = (bool) (config('services.facebook.client_id') ?: env('META_APP_ID'));
        if (!$configured) return response()->json(['message' => 'Meta OAuth is not configured on the server.', 'code' => 'META_NOT_CONFIGURED'], 503);
        return response()->json(['message' => 'Meta OAuth is configured. Start the existing Meta OAuth flow from CommerceAutomation.', 'code' => 'META_OAUTH_HANDLED_BY_CONNECTOR']);
    }

    public function whatsappStatus(): mixed
    {
        $row = $this->storedIntegrations()['whatsapp'] ?? null;
        $configured = (bool) (env('WHATSAPP_ACCESS_TOKEN') && (env('WHATSAPP_PHONE_NUMBER_ID') || env('WHATSAPP_PHONE_ID')));
        return response()->json(['data' => [
            'key' => 'whatsapp',
            'configured' => $configured,
            'connected' => $this->connected('whatsapp', $row) || $configured,
            'credential_source' => 'server',
        ]]);
    }

    public function whatsappTemplates(Request $request): mixed
    {
        if (!Schema::connection('priyasa')->hasTable('commerce_templates')) return response()->json(['data' => [], 'meta' => ['page' => 1, 'per_page' => 50, 'total' => 0]]);
        $columns = Schema::connection('priyasa')->getColumnListing('commerce_templates');
        $query = DB::connection('priyasa')->table('commerce_templates');
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search, $columns) {
                foreach (array_intersect(['name', 'template_name', 'status', 'language'], $columns) as $column) $q->orWhere($column, 'like', '%'.$search.'%');
            });
        }
        if (in_array('id', $columns, true)) $query->orderByDesc('id');
        return response()->json(['data' => $query->limit(200)->get()]);
    }

    public function whatsappSyncTemplates(): mixed
    {
        return response()->json(['message' => 'WhatsApp template synchronization is owned by the WhatsApp connector. No duplicate sync is performed by PriyasaCore.', 'code' => 'WHATSAPP_SYNC_MANAGED_BY_CONNECTOR'], 409);
    }

    private function storedIntegrations(): array
    {
        if (!Schema::connection('priyasa')->hasTable('commerce_integrations')) return [];
        try {
            return DB::connection('priyasa')->table('commerce_integrations')->get()->mapWithKeys(function ($i): array {
                $key = (string) ($i->key ?? '');
                return [$key => [
                    'name' => $i->name ?? null,
                    'enabled' => $i->enabled ?? false,
                    'connected_at' => $i->connected_at ?? null,
                    'last_tested_at' => $i->last_tested_at ?? null,
                    'last_error' => $i->last_error ?? null,
                    'credential_source' => $i->credential_source ?? 'server',
                ]];
            })->all();
        } catch (Throwable $e) {
            report($e);
            return [];
        }
    }

    private function connected(string $key, ?array $row): bool
    {
        if (!$row) return false;
        return (bool) ($row['enabled'] ?? false) && !empty($row['connected_at']);
    }
}
