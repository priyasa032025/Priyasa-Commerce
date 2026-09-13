<?php
namespace Modules\PriyasaCore\Services;
use Illuminate\Support\Facades\Http;
use Modules\PriyasaCore\Models\NotificationDevice;
use RuntimeException;
final class FcmNotificationService {
    public function send(NotificationDevice $device, string $title, string $body, array $data=[]): array {
        if (!config('p28_notifications.fcm.enabled')) throw new RuntimeException('FCM is disabled.');
        $project=(string)config('p28_notifications.fcm.project_id'); $token=(string)config('p28_notifications.fcm.access_token');
        if ($project==='' || $token==='') throw new RuntimeException('FCM project/access token is not configured.');
        $endpoint=sprintf((string)config('p28_notifications.fcm.endpoint'), $project);
        $payload=['message'=>['token'=>$device->fcm_token,'notification'=>['title'=>$title,'body'=>$body],'data'=>array_map('strval',$data)]];
        $r=Http::withToken($token)->acceptJson()->post($endpoint,$payload);
        if ($r->successful()) return $r->json() ?: [];
        $status=$r->status();
        if (in_array($status,[400,404],true)) { $device->update(['enabled'=>false,'invalidated_at'=>now()]); }
        throw new RuntimeException('FCM delivery failed (HTTP '.$status.'): '.mb_substr($r->body(),0,500));
    }
}
