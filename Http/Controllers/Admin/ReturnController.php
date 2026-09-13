<?php
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use Illuminate\Http\Request; use Modules\PriyasaCore\Models\ReturnRequest; use Modules\PriyasaCore\Services\ReturnService;
class ReturnController extends Controller { public function index(){return response()->json(['success'=>true,'data'=>ReturnRequest::with(['order','customer'])->latest()->paginate(config('priyasacore.per_page'))]);} public function status(Request $r,ReturnRequest $return,ReturnService $s){$d=$r->validate(['status'=>'required|string','note'=>'nullable|string|max:1000']);return response()->json(['success'=>true,'data'=>$s->transition($return,$d['status'],$d['note']??null)]);} }
