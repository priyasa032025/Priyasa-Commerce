<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Review;
final class ReviewController extends Controller {
 public function index(Request $r){$q=Review::query()->with(['product:id,name','customer:id,first_name,last_name,phone'])->orderByDesc('created_at'); if($s=trim((string)$r->query('search','')))$q->where(fn($x)=>$x->where('title','like',"%$s%")->orWhere('body','like',"%$s%")); if($st=$r->query('status'))$q->where('status',$st); return response()->json(['success'=>true,'data'=>$q->paginate(min(max($r->integer('per_page',25),1),100))]);}
 public function status(Request $r, Review $review){$data=$r->validate(['status'=>'required|in:pending,approved,rejected,hidden']); $review->update(['status'=>$data['status']]); return response()->json(['success'=>true,'data'=>$review->fresh()->load(['product:id,name','customer:id,first_name,last_name,phone'])]);}
}
