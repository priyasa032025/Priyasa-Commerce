<?php
namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Services\FinancialReportService;

final class FinancialReportsController extends Controller
{
    public function __construct(private FinancialReportService $service) {}
    private function dates(Request $r): array { $f=Carbon::parse($r->input('from',now()->subDays(30)->toDateString())); $t=Carbon::parse($r->input('to',now()->toDateString())); abort_if($t->lt($f),422,'Invalid date range'); abort_if($f->diffInDays($t)>366,422,'Maximum range is 366 days'); return [$f,$t]; }
    private function respond($rows, Request $r, string $name) { if ($r->boolean('download')) return $this->csv($rows,$name); return response()->json(['data'=>$rows,'count'=>$rows->count()]); }
    private function csv($rows,string $name) { $rows=$rows->map(fn($r)=>(array)$r)->values(); $headers=$rows->isEmpty()?[]:array_keys($rows->first()); return response()->streamDownload(function()use($rows,$headers){$out=fopen('php://output','w'); if($headers) fputcsv($out,$headers); foreach($rows as $r) fputcsv($out,array_map(fn($v)=>is_array($v)?json_encode($v):$v,$r)); fclose($out);},$name.'.csv',['Content-Type'=>'text/csv']); }
    public function tax(Request $r){[$f,$t]=$this->dates($r);return $this->respond($this->service->tax($f,$t,(int)$r->input('limit',5000)),$r,'tax-report');}
    public function invoices(Request $r){[$f,$t]=$this->dates($r);return $this->respond($this->service->invoices($f,$t,(int)$r->input('limit',5000)),$r,'invoice-register');}
    public function refunds(Request $r){[$f,$t]=$this->dates($r);return $this->respond($this->service->refunds($f,$t,(int)$r->input('limit',5000)),$r,'refund-register');}
    public function wallet(Request $r){[$f,$t]=$this->dates($r);return $this->respond($this->service->wallet($f,$t,(int)$r->input('limit',5000)),$r,'wallet-ledger');}
    public function loyalty(Request $r){[$f,$t]=$this->dates($r);return $this->respond($this->service->loyalty($f,$t,(int)$r->input('limit',5000)),$r,'loyalty-ledger');}
    public function closing(Request $r){$d=Carbon::parse($r->input('date',now()->toDateString()));return response()->json($this->service->closing($d));}
}
