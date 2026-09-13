<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Jobs\ReconcilePaymentWebhook;
use Modules\PriyasaCore\Models\WebhookEvent;
use Modules\PriyasaCore\Integrations\Payments\RazorpayGateway;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, RazorpayGateway $gateway): Response
    {
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature', '');
        if (!$gateway->verifyWebhook($raw, $signature)) return response()->json(['success'=>false,'error'=>['code'=>'INVALID_SIGNATURE','message'=>'Invalid webhook signature.']], 401);

        $payload = json_decode($raw, true);
        if (!is_array($payload)) return response()->json(['success'=>false,'error'=>['code'=>'INVALID_PAYLOAD','message'=>'Invalid JSON payload.']], 400);
        $eventType = (string) ($payload['event'] ?? 'unknown');
        $eventId = (string) ($request->header('X-Razorpay-Event-Id', '') ?: hash('sha256', $raw));
        $event = DB::connection('priyasa')->transaction(function () use ($eventType, $eventId, $payload): WebhookEvent {
            return WebhookEvent::firstOrCreate(['provider'=>'razorpay','event_id'=>$eventId], ['event_type'=>$eventType,'status'=>'received','payload'=>$payload]);
        });
        if ($event->status !== 'processed') ReconcilePaymentWebhook::dispatch($event->id);
        return response()->json(['success'=>true,'data'=>['accepted'=>true,'event_id'=>$eventId]], 202);
    }
}
