<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class AutomationController extends Controller
{
    public function index(Request $request): mixed
    {
        if (!Schema::connection('priyasa')->hasTable('commerce_automations')) {
            return response()->json(['data' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 50, 'data' => []]]);
        }
        $perPage = min(max((int) $request->integer('per_page', 50), 1), 100);
        $query = DB::connection('priyasa')->table('commerce_automations');
        $columns = Schema::connection('priyasa')->getColumnListing('commerce_automations');
        if (($search = trim((string) $request->input('search', ''))) !== '') {
            $query->where(function ($q) use ($search, $columns) {
                foreach (array_intersect(['event_key', 'name', 'key', 'description'], $columns) as $column) $q->orWhere($column, 'like', '%'.$search.'%');
            });
        }
        if ($request->filled('status') && in_array('enabled', $columns, true)) $query->where('enabled', filter_var($request->input('status'), FILTER_VALIDATE_BOOLEAN));
        $p = $query->orderBy(in_array('id', $columns, true) ? 'id' : $columns[0])->paginate($perPage);
        return response()->json(['data' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total(), 'per_page' => $p->perPage(), 'data' => $p->items()]]);
    }

    public function update(Request $request, string $automation): mixed
    {
        if (!Schema::connection('priyasa')->hasTable('commerce_automations')) return response()->json(['message' => 'Automation storage is not available.'], 503);
        $columns = Schema::connection('priyasa')->getColumnListing('commerce_automations');
        $allowed = array_intersect(['enabled','email_enabled','whatsapp_enabled','fcm_enabled','email_template','whatsapp_template','fcm_template','delay_seconds','conditions'], $columns);
        $rules = [];
        foreach ($allowed as $column) {
            $rules[$column] = match ($column) {
                'enabled','email_enabled','whatsapp_enabled','fcm_enabled' => 'sometimes|boolean',
                'delay_seconds' => 'sometimes|integer|min:0|max:604800',
                'conditions' => 'sometimes|array',
                default => 'sometimes|nullable|string|max:10000',
            };
        }
        $data = $request->validate($rules);
        if (in_array('updated_at', $columns, true)) $data['updated_at'] = now();
        $query = DB::connection('priyasa')->table('commerce_automations')->where('id', $automation);
        $updated = $query->update($data);
        if (!$updated && !$query->exists()) return response()->json(['message' => 'Automation not found.'], 404);
        return response()->json(['data' => DB::connection('priyasa')->table('commerce_automations')->where('id', $automation)->first(), 'message' => 'Automation updated.']);
    }

    public function toggle(string $automation): mixed
    {
        if (!Schema::connection('priyasa')->hasTable('commerce_automations')) return response()->json(['message' => 'Automation storage is not available.'], 503);
        $columns = Schema::connection('priyasa')->getColumnListing('commerce_automations');
        if (!in_array('enabled', $columns, true)) return response()->json(['message' => 'Automation table does not support enabled state.'], 503);
        $row = DB::connection('priyasa')->table('commerce_automations')->where('id', $automation)->first();
        if (!$row) return response()->json(['message' => 'Automation not found.'], 404);
        $values = ['enabled' => !(bool) $row->enabled];
        if (in_array('updated_at', $columns, true)) $values['updated_at'] = now();
        DB::connection('priyasa')->table('commerce_automations')->where('id', $automation)->update($values);
        return response()->json(['data' => DB::connection('priyasa')->table('commerce_automations')->where('id', $automation)->first(), 'message' => 'Automation updated.']);
    }
}
