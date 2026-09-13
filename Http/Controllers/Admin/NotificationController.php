<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Http\Controllers\Controller;
use Modules\PriyasaCore\Services\NotificationAutomationService;
final class NotificationController extends Controller {
 public function templates(){return response()->json(['data'=>DB::connection('priyasa')->table('priyasa_notification_templates')->orderBy('channel')->orderBy('key')->get()]);}
 public function storeTemplate(Request $r){$d=$r->validate(['key'=>'required|string|max:120','channel'=>'required|in:whatsapp,push,email,sms','locale'=>'nullable|string|max:20','subject'=>'nullable|string|max:255','body'=>'required|string','metadata'=>'nullable|array','is_active'=>'nullable|boolean']);$id=DB::connection('priyasa')->table('priyasa_notification_templates')->insertGetId(array_merge($d,['locale'=>$d['locale']??'en-IN','metadata'=>isset($d['metadata'])?json_encode($d['metadata']):null,'created_at'=>now(),'updated_at'=>now()]));return response()->json(['data'=>DB::connection('priyasa')->table('priyasa_notification_templates')->find($id)],201);}
 public function outbox(Request $r){$q=DB::connection('priyasa')->table('priyasa_notification_outbox')->orderByDesc('id');if($r->filled('status'))$q->where('status',$r->string('status'));if($r->filled('channel'))$q->where('channel',$r->string('channel'));return response()->json(['data'=>$q->paginate(min(100,max(1,(int)$r->query('per_page',25))))]);}
 public function queueTest(Request $r,NotificationAutomationService $svc){$d=$r->validate(['customer_id'=>'required|integer','channel'=>'required|in:whatsapp,push,email,sms','recipient'=>'required|string|max:320','template_key'=>'nullable|string|max:120','event_key'=>'required|string|max:120','payload'=>'nullable|array']);$id=$svc->queue((int)$d['customer_id'],$d['channel'],$d['event_key'],$d['recipient'],$d['template_key']??null,$d['payload']??[]);return response()->json(['data'=>['outbox_id'=>$id]],202);}
}
