<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Services\ReviewService;

final class ReviewController extends Controller
{
    public function index(Request $request, int $product): array
    {
        $customer=$request->user()?->customer;
        return app(ReviewService::class)->list($product,$customer,(int)$request->integer('page',1),(int)$request->integer('per_page',10),$request->filled('rating')?(int)$request->integer('rating'):null,(string)$request->input('sort','newest'));
    }
    public function store(Request $request): array
    {
        $data=$request->validate(['product_id'=>['required','integer','min:1'],'variant_id'=>['nullable','integer','min:1'],'order_id'=>['nullable','integer','min:1'],'order_item_id'=>['nullable','integer','min:1'],'rating'=>['required','integer','min:1','max:5'],'title'=>['nullable','string','max:160'],'body'=>['nullable','string','max:5000'],'media'=>['nullable','array','max:6'],'media.*'=>['string','url','max:2048']]);
        $customer=$request->user()?->customer;
        abort_unless($customer instanceof Customer,401);
        return app(ReviewService::class)->create($customer,$data);
    }
    public function vote(Request $request, int $review): array
    {
        $data=$request->validate(['helpful'=>['required','boolean']]); $customer=$request->user()?->customer; abort_unless($customer instanceof Customer,401);
        return app(ReviewService::class)->vote($customer,$review,(bool)$data['helpful']);
    }
    public function mine(Request $request): array
    {
        $customer=$request->user()?->customer; abort_unless($customer instanceof Customer,401);
        $rows=\Modules\PriyasaCore\Models\ProductReview::where('customer_id',$customer->getKey())->orderByDesc('created_at')->paginate(min(50,max(1,(int)$request->integer('per_page',20))));
        return ['items'=>$rows->items(),'pagination'=>['page'=>$rows->currentPage(),'per_page'=>$rows->perPage(),'total'=>$rows->total(),'last_page'=>$rows->lastPage(),'has_more'=>$rows->hasMorePages()]];
    }
}
