<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use Modules\PriyasaCore\Models\Order; use Modules\PriyasaCore\Services\InvoiceService;
final class InvoiceController extends Controller { public function issue(Order $order, InvoiceService $service){return response()->json(['success'=>true,'data'=>$service->issue($order)->fresh()]);} public function show(Order $order){$invoice=$order->invoice()->first(); abort_unless($invoice,404,'Invoice not found.'); return response()->json(['success'=>true,'data'=>$invoice]);} }
