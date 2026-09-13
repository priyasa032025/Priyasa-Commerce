<?php
namespace Modules\PriyasaCore\Jobs;
use Carbon\Carbon; use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Modules\PriyasaCore\Services\FinancialReconciliationService;
final class ProcessFinancialClosing implements ShouldQueue { use Queueable; public function __construct(public string $date){} public function handle(FinancialReconciliationService $service):void { $d=Carbon::parse($this->date); $o=$service->overview($d,$d); \Illuminate\Support\Facades\DB::connection('priyasa')->table('priyasa_financial_daily')->updateOrInsert(['metric_date'=>$d->toDateString()],['gross_sales'=>$o['gross_sales'],'refunds'=>$o['refunds'],'net_sales'=>$o['net_sales'],'updated_at'=>now(),'created_at'=>now()]); } }
