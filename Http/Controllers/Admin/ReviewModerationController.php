<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Models\ProductReview;
use Modules\PriyasaCore\Services\ReviewService;

final class ReviewModerationController extends Controller
{
    public function index(Request $request): array
    {
        $q=ProductReview::query()->orderByDesc('created_at');
        if ($request->filled('status')) $q->where('status',(string)$request->input('status'));
        if ($request->filled('product_id')) $q->where('product_id',(int)$request->input('product_id'));
        $rows=$q->paginate(min(100,max(1,(int)$request->integer('per_page',30))));
        return ['items'=>$rows->items(),'pagination'=>['page'=>$rows->currentPage(),'per_page'=>$rows->perPage(),'total'=>$rows->total(),'last_page'=>$rows->lastPage(),'has_more'=>$rows->hasMorePages()]];
    }
    public function moderate(Request $request,int $review): array
    {
        $data=$request->validate(['status'=>['required','in:pending,approved,rejected'],'note'=>['nullable','string','max:2000']]);
        return app(ReviewService::class)->moderate($review,$data['status'],$data['note']??null);
    }
}
