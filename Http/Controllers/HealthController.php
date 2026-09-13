<?php
namespace Modules\PriyasaCore\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
class HealthController extends Controller { public function __invoke(){ $db='ok';$redis='ok';try{DB::connection('priyasa')->select('select 1');}catch(\Throwable){$db='down';}try{Redis::connection()->ping();}catch(\Throwable){$redis='down';} $ok=$db==='ok'&&$redis==='ok';return response()->json(['success'=>$ok,'data'=>['database'=>$db,'redis'=>$redis,'service'=>'priyasa-core']],$ok?200:503); } }
