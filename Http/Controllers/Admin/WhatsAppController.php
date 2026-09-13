<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class WhatsAppController extends Controller
{
    public function conversations(Request $request): mixed
    {
        if (!Schema::connection('priyasa')->hasTable('commerce_whatsapp_messages')) return response()->json(['data' => [], 'meta' => ['page' => 1, 'per_page' => 50, 'total' => 0]]);
        $columns = Schema::connection('priyasa')->getColumnListing('commerce_whatsapp_messages');
        if (!in_array('phone', $columns, true)) return response()->json(['data' => [], 'meta' => ['page' => 1, 'per_page' => 50, 'total' => 0]]);
        $perPage = min(max((int) $request->integer('per_page', 50), 1), 100);
        $page = max((int) $request->integer('page', 1), 1);
        $q = DB::connection('priyasa')->table('commerce_whatsapp_messages');
        if ($search = trim((string) $request->input('search', ''))) {
            $digits = preg_replace('/\D+/', '', $search);
            $q->where('phone', 'like', '%'.($digits ?: $search).'%');
        }
        $messages = $q->orderByDesc(in_array('id', $columns, true) ? 'id' : $columns[0])->limit(1000)->get()->groupBy('phone');
        $rows = $messages->map(static function ($items, $phone): array {
            $latest = $items->first();
            return [
                'phone' => (string) $phone,
                'message_count' => $items->count(),
                'latest_at' => $latest->created_at ?? null,
                'latest_direction' => $latest->direction ?? null,
                'latest_message' => $latest->message ?? ($latest->body ?? null),
            ];
        })->sortByDesc('latest_at')->values();
        return response()->json(['data' => $rows->forPage($page - 1, $perPage)->values(), 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $rows->count()]]);
    }

    public function conversation(string $phone): mixed
    {
        if (!Schema::connection('priyasa')->hasTable('commerce_whatsapp_messages')) return response()->json(['data' => []]);
        $columns = Schema::connection('priyasa')->getColumnListing('commerce_whatsapp_messages');
        if (!in_array('phone', $columns, true)) return response()->json(['data' => []]);
        $phone = preg_replace('/\D+/', '', $phone);
        $query = DB::connection('priyasa')->table('commerce_whatsapp_messages')->where('phone', $phone);
        if (in_array('id', $columns, true)) $query->orderByDesc('id');
        return response()->json(['data' => $query->limit(500)->get()->reverse()->values()]);
    }

    public function reply(Request $request, string $phone): mixed
    {
        return response()->json([
            'message' => 'Sending WhatsApp messages is handled by the configured WhatsApp connector. PriyasaCore does not duplicate provider delivery logic.',
            'code' => 'WHATSAPP_SEND_MANAGED_BY_CONNECTOR',
        ], 503);
    }
}
