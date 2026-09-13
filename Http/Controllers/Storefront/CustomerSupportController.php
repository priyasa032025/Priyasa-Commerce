<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Storefront;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Services\CustomerResolver;
use Modules\PriyasaCore\Services\CustomerSupportService;
use RuntimeException;

final class CustomerSupportController extends Controller
{
    public function index(Request $r, CustomerSupportService $s){$c=app(CustomerResolver::class)->resolveAuthenticated($r);return $s->list($c,(int)$r->integer('per_page',20));}
    public function categories(CustomerSupportService $s){return ['items'=>$s->categories()];}
    public function show(Request $r,int $ticket,CustomerSupportService $s){$c=app(CustomerResolver::class)->resolveAuthenticated($r);return $s->detail($c,$ticket);}
    public function create(Request $r,CustomerSupportService $s){$c=app(CustomerResolver::class)->resolveAuthenticated($r);return $s->create($c,$r->validate(['category'=>'nullable|string','subject'=>'nullable|string|max:180','message'=>'required|string|max:10000','order_id'=>'nullable|integer','order_item_id'=>'nullable|integer','priority'=>'nullable|string','attachments'=>'nullable|array','metadata'=>'nullable|array']));}
    public function reply(Request $r,int $ticket,CustomerSupportService $s){$c=app(CustomerResolver::class)->resolveAuthenticated($r);return $s->reply($c,$ticket,$r->validate(['message'=>'required|string|max:10000','attachments'=>'nullable|array']));}
    public function close(Request $r,int $ticket,CustomerSupportService $s){$c=app(CustomerResolver::class)->resolveAuthenticated($r);return $s->close($c,$ticket);}
}
