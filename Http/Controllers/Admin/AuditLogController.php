<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\AuditLog;

final class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->latest('id');
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('actor_id', 'like', "%{$search}%")
                  ->orWhere('entity_type', 'like', "%{$search}%")
                  ->orWhere('entity_id', 'like', "%{$search}%")
                  ->orWhere('request_id', 'like', "%{$search}%")
                  ->orWhere('ip', 'like', "%{$search}%");
            });
        }
        foreach (['action','entity_type','actor_type'] as $field) {
            if ($value = trim((string) $request->input($field))) $query->where($field, $value);
        }
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->date('from'));
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->date('to'));

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
        $logs = $query->paginate($perPage);
        $logs->getCollection()->each(function (AuditLog $log): void {
            if (is_array($log->data)) {
                $log->data = $this->redact($log->data);
            }
        });
        return response()->json(['success'=>true,'data'=>['audit_logs'=>$logs]]);
    }

    private function redact(array $data): array
    {
        $sensitive = ['password','password_confirmation','token','access_token','refresh_token','secret','api_key','authorization','signature'];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) $data[$key] = '[REDACTED]';
            elseif (is_array($value)) $data[$key] = $this->redact($value);
        }
        return $data;
    }
}
