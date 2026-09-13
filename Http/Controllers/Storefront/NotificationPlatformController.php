<?php
namespace Modules\PriyasaCore\Http\Controllers\Storefront;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Models\NotificationInbox;
use Modules\PriyasaCore\Services\NotificationPlatformService;
use Modules\PriyasaCore\Services\StorefrontApiContract;
class NotificationPlatformController extends Controller {
 public function register(Request $r, NotificationPlatformService $s){$d=$r->validate(['device_id'=>'required|string|max:191','platform'=>'nullable|in:android,ios,web','fcm_token'=>'required|string|max:4096','customer_id'=>'nullable|integer','metadata'=>'nullable|array']); $d=$s->registerDevice($r->user(),$d); return response()->json(app(StorefrontApiContract::class)->success('notification.device.registered',['device_id'=>$d->device_id,'platform'=>$d->platform,'enabled'=>(bool)$d->enabled]));}
 public function unregister(Request $r,NotificationPlatformService $s){$r->validate(['device_id'=>'required|string|max:191']);$s->invalidateDevice($r->user(),$r->string('device_id')->toString());return response()->json(app(StorefrontApiContract::class)->success('notification.device.unregistered',null));}
 public function inbox(Request $r){$per=min(max((int)$r->integer('per_page',30),1),100);$q=NotificationInbox::where('user_id',$r->user()->id)->where(function($x){$x->whereNull('expires_at')->orWhere('expires_at','>',now());})->latest();$p=$q->paginate($per);return response()->json(app(StorefrontApiContract::class)->success('notification.inbox',$p->items(),['pagination'=>['current_page'=>$p->currentPage(),'per_page'=>$p->perPage(),'total'=>$p->total(),'last_page'=>$p->lastPage()]]));}
 public function unread(Request $r,NotificationPlatformService $s){return response()->json(app(StorefrontApiContract::class)->success('notification.unread',['count'=>$s->unreadCount($r->user())]));}
 public function read(Request $r,$id,NotificationPlatformService $s){$s->markRead($r->user(),$id);return response()->json(app(StorefrontApiContract::class)->success('notification.read',['id'=>(int)$id]));}
}
