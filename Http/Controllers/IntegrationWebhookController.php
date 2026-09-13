<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Integrations\Payments\RazorpayGateway;
use Modules\PriyasaCore\Models\WebhookEvent;

class IntegrationWebhookController extends Controller
{
    public function handle(Request $request, string $provider)
    {
        $raw=$request->getContent();
        if (!in_array($provider, ['razorpay','woocommerce'], true)) return response()->json(['success'=>false,'message'=>'Unsupported webhook provider.'],404);
        if ($provider === 'woocommerce') {
            $secret=(string)config('priyasacore.woocommerce.webhook_secret');
            $signature=(string)$request->header('X-WC-Webhook-Signature');
            $expected=$secret!=='' ? base64_encode(hash_hmac('sha256',$raw,$secret,true)) : '';
            if ($expected==='' || !hash_equals($expected,$signature)) return response()->json(['success'=>false,'message'=>'Invalid WooCommerce webhook signature.'],401);
            $topic=(string)$request->header('X-WC-Webhook-Topic','unknown');
            $eventId=(string)($request->header('X-WC-Webhook-Delivery') ?: hash('sha256',$provider.'|'.$topic.'|'.$raw));
            $event=WebhookEvent::firstOrCreate(['provider'=>$provider,'event_id'=>$eventId],['event_type'=>$topic,'payload'=>$request->all(),'status'=>'received']);
            if ($event->wasRecentlyCreated) DB::connection('priyasa')->afterCommit(fn()=>dispatch(new \Modules\PriyasaCore\Jobs\ProcessWebhookEvent($event->id)));
            return response()->json(['success'=>true,'data'=>['received'=>true,'event_id'=>$eventId]],202);
        }
        if ($provider === 'razorpay') {
            $signature=(string)$request->header('X-Razorpay-Signature');
            if (! app(RazorpayGateway::class)->verifyWebhook($raw,$signature)) return response()->json(['success'=>false,'message'=>'Invalid webhook signature.'],401);
        }
        $eventId=(string)($request->header('X-Razorpay-Event-Id') ?: $request->header('X-Event-Id') ?: hash('sha256',$provider.'|'.$raw));
        $event=WebhookEvent::firstOrCreate(['provider'=>$provider,'event_id'=>$eventId],['event_type'=>(string)($request->input('event') ?: $request->header('X-Event-Type') ?: 'unknown'),'payload'=>$request->all(),'status'=>'received']);
        if ($event->wasRecentlyCreated) DB::connection('priyasa')->afterCommit(fn()=>dispatch(new \Modules\PriyasaCore\Jobs\ProcessWebhookEvent($event->id)));
        return response()->json(['success'=>true,'data'=>['received'=>true,'event_id'=>$eventId]],202);
    }
}
