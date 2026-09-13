<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Services\CustomerSupportService;

final class CustomerSupportController extends Controller
{
    public function index(Request $r,CustomerSupportService $s){return $s->adminList($r->all());}
    public function show(int $ticket,CustomerSupportService $s){$t=\Modules\PriyasaCore\Models\SupportTicket::with(['messages','customer','order'])->findOrFail($ticket);return ['ticket'=>$t];}
    public function update(Request $r,int $ticket,CustomerSupportService $s){return $s->adminUpdate($ticket,$r->validate(['status'=>'nullable|string','priority'=>'nullable|string','assigned_to'=>'nullable|string|max:128']),(string)optional($r->user())->id);}
    public function reply(Request $r,int $ticket,CustomerSupportService $s){return $s->adminReply($ticket,$r->validate(['message'=>'required|string|max:10000','attachments'=>'nullable|array']),(string)optional($r->user())->id);}
}
