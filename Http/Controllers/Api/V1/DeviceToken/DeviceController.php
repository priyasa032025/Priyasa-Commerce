<?php

namespace Modules\PriyasaCore\Http\Controllers\Api\V1\DeviceToken;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\DeviceToken;
use Modules\PriyasaCore\Services\DeviceTokenLifecycleService;
use App\Models\PushTelemetryEvent;


class DeviceController extends Controller
{
    public function __construct(private DeviceTokenLifecycleService $devices) {}

    public function register(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'guest_id' => ['nullable', 'string', 'max:255'],
            'device' => ['nullable', 'string', 'max:255'],
            'browser' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'max:50'],
            'channel' => ['nullable', 'string', 'max:50'],
            'delivery_target' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:255'],
        ]);

        $data = $this->applyAuthenticatedIdentity($request, $data);
        $device = $this->devices->register($data);

        return response()->json([
            'success' => true,
            'message' => 'Device registered successfully',
            'data' => $device,
        ]);
    }

    public function refresh(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'guest_id' => ['nullable', 'string', 'max:255'],
            'device' => ['nullable', 'string', 'max:255'],
            'browser' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'max:50'],
            'channel' => ['nullable', 'string', 'max:50'],
            'delivery_target' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:255'],
        ]);

        $data = $this->applyAuthenticatedIdentity($request, $data);
        $device = $this->devices->refresh($data);

        return response()->json([
            'success' => true,
            'message' => 'Device refreshed successfully',
            'data' => $device,
        ]);
    }

    public function updateUser(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:4096'],
            'user_id' => ['nullable', 'integer'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'guest_id' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:255'],
            'device' => ['nullable', 'string', 'max:255'],
            'browser' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'max:50'],
            'channel' => ['nullable', 'string', 'max:50'],
            'delivery_target' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($data['device_id']) && empty($data['token'])) {
            return response()->json([
                'success' => false,
                'message' => 'device_id or token is required',
            ], 422);
        }

        $data = $this->applyAuthenticatedIdentity($request, $data, true);
        $device = $this->devices->updateDevice($data);

        return response()->json([
            'success' => true,
            'message' => 'User linked successfully',
            'data' => $device,
        ]);
    }

    public function logout(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:4096'],
        ]);

        $data = $this->applyAuthenticatedIdentity($request, $data);
        $this->devices->logout($data);

        return response()->json([
            'success' => true,
            'message' => 'Device logged out',
        ]);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:4096'],
        ]);

        $data = $this->applyAuthenticatedIdentity($request, $data);
        $this->devices->destroyDevice($data);

        return response()->json([
            'success' => true,
            'message' => 'Device removed',
        ]);
    }

    public function userDevices($userId, Request $request)
    {
        abort_unless($this->isTrusted($request), 401, 'Unauthorized');

        return response()->json(
            DeviceToken::where('user_id', $userId)->get()
        );
    }
    
    public function telemetry(Request $request) {
      $data=$request->validate([
       'event'=>'required|string|max:64','status'=>'nullable|string|max:32','detail'=>'nullable|string|max:300',
       'device_id'=>'required|string|max:100','session_id'=>'required|string|max:100','browser'=>'nullable|string|max:50',
       'os'=>'nullable|string|max:50','device_type'=>'nullable|string|max:30','in_app_browser'=>'nullable|string|max:50',
       'permission'=>'nullable|string|max:30','path'=>'nullable|string|max:500','landing_path'=>'nullable|string|max:500',
       'referrer'=>'nullable|string|max:500','source'=>'nullable|string|max:100','plugin_version'=>'nullable|string|max:30'
      ]);
      //$data['ip_hash']=hash_hmac('sha256',(string)$request->ip(),config('app.key'));
      PushTelemetryEvent::create($data);
      return response()->json(['ok'=>true],201);
     }
    public function activeDevices(Request $request)
    {
    abort_unless($this->isTrusted($request), 401, 'Unauthorized');

    $devices = DeviceToken::where('is_active', 1)
        ->whereNotNull('token')
        ->where('token', '!=', '')
        ->get();

    return response()->json([
        'success' => true,
        'count'   => $devices->count(),
        'data'    => $devices,
    ]);
    }
    /**
     * Authenticated identity always wins over client-supplied user_id/phone.
     * Guest registration is explicitly anonymous.
     */
    private function applyAuthenticatedIdentity(Request $request, array $data, bool $requireAuth = false): array
    {
        $user = $request->user('sanctum');

        if ($requireAuth) {
            abort_unless($user, 401, 'Unauthenticated.');
        }

        if ($user) {
            $data['user_id'] = $user->getKey();
            $data['phone_number'] = $user->mobile ?? $user->phone ?? ($data['phone_number'] ?? null);
        } else {
            unset($data['user_id'], $data['phone_number']);
        }

        return $data;
    }

    private function isTrusted(Request $request): bool
    {
        $expected = (string) env('INTERNAL_API_TOKEN', '');
        $provided = (string) $request->bearerToken();

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }
}
