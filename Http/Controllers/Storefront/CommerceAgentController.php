<?php
namespace Modules\PriyasaCore\Http\Controllers\Storefront;
use Illuminate\Http\Request; use Illuminate\Routing\Controller; use Modules\PriyasaCore\Services\CommerceAgentService;
class CommerceAgentController extends Controller { public function message(Request $r,CommerceAgentService $agent){$data=$r->validate(['channel'=>'required|string|max:40','conversation_id'=>'required|string|max:191','message'=>'required|string|max:4000']); $customerId=optional($r->user()?->customer)->id; return response()->json($agent->handle($data['channel'],$data['conversation_id'],$data['message'],$customerId));}}
