<?php
namespace Modules\PriyasaCore\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\PriyasaCore\Models\NotificationDevice;
use Modules\PriyasaCore\Models\NotificationInbox;
final class NotificationPlatformService {
    public function registerDevice($user, array $input): NotificationDevice {
        $token=(string)$input['fcm_token'];
        $hash=hash('sha256',$token);
        return DB::connection('priyasa')->transaction(function() use($user,$input,$token,$hash){
            $device=NotificationDevice::where('device_id',$input['device_id'])->first();
            if ($device && $device->user_id !== $user->id) $device=null;
            if (!$device) $device=NotificationDevice::firstOrNew(['user_id'=>$user->id,'device_id'=>$input['device_id']]);
            $device->fill(['customer_id'=>$input['customer_id'] ?? null,'platform'=>$input['platform'] ?? 'android','fcm_token'=>$token,'token_hash'=>$hash,'enabled'=>true,'invalidated_at'=>null,'last_seen_at'=>now(),'metadata'=>$input['metadata'] ?? null]);
            $device->save(); return $device;
        });
    }
    public function invalidateDevice($user,string $deviceId): void { NotificationDevice::where('user_id',$user->id)->where('device_id',$deviceId)->update(['enabled'=>false,'invalidated_at'=>now()]); }
    public function createInbox(array $n): NotificationInbox {
        return NotificationInbox::firstOrCreate(['dedupe_key'=>$n['dedupe_key']],$n);
    }
    public function markRead($user,$id): bool { return NotificationInbox::where('id',$id)->where('user_id',$user->id)->whereNull('read_at')->update(['read_at'=>now()])>0; }
    public function unreadCount($user): int { return (int)NotificationInbox::where('user_id',$user->id)->whereNull('read_at')->where(function($q){$q->whereNull('expires_at')->orWhere('expires_at','>',now());})->count(); }
}
