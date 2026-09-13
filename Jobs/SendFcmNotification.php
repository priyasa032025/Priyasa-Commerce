<?php
namespace Modules\PriyasaCore\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\PriyasaCore\Models\NotificationDevice;
use Modules\PriyasaCore\Services\FcmNotificationService;
class SendFcmNotification implements ShouldQueue {
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
 public int $tries=4;
 public function __construct(public int $deviceId, public string $title, public string $body, public array $data=[], public ?int $inboxId=null) {}
 public function backoff(): array { return [30,120,600]; }
 public function handle(FcmNotificationService $fcm): void { $d=NotificationDevice::find($this->deviceId); if(!$d || !$d->enabled) return; try{$fcm->send($d,$this->title,$this->body,$this->data);}catch(\Throwable $e){ if($this->attempts() >= $this->tries) throw $e; throw $e; } }
}
