<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Http\Controllers\Controller;
final class StorefrontNotificationController extends Controller {
 public function preferences(Request $r){$customer=$r->user()?->customer; if(!$customer)return response()->json(['data'=>null]);$row=DB::connection('priyasa')->table('priyasa_notification_preferences')->where('customer_id',$customer->id)->first();return response()->json(['data'=>$row?json_decode($row->preferences,true):[]]);}
 public function updatePreferences(Request $r){$customer=$r->user()?->customer;abort_unless($customer,401);$prefs=$r->validate(['preferences'=>'required|array']);DB::connection('priyasa')->table('priyasa_notification_preferences')->updateOrInsert(['customer_id'=>$customer->id],['preferences'=>json_encode($prefs['preferences']),'updated_at'=>now(),'created_at'=>now()]);return response()->json(['data'=>$prefs['preferences']]);}
}
