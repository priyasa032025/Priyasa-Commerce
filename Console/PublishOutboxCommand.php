<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\PriyasaCore\Models\OutboxEvent;

class PublishOutboxCommand extends Command
{
    protected $signature='priyasa:publish-outbox';
    protected $description='Publish pending Priyasa outbox events';
    public function handle(): int {
        OutboxEvent::whereNull('published_at')->orderBy('id')->limit(500)->get()->each(function($event){
            try {
                $payload=(array)$event->payload;
                match($event->event_type) {
                    'order.status.changed' => event(new \Modules\PriyasaCore\Events\OrderStatusChanged(\Modules\PriyasaCore\Models\Order::findOrFail($payload['order_id']), (string)$payload['from'], (string)$payload['to'])),
                    default => Log::warning('Unknown Priyasa outbox event', ['event_type'=>$event->event_type,'id'=>$event->id]),
                };
                $event->increment('attempts');
                $event->update(['published_at'=>now(),'last_error'=>null]);
            } catch(\Throwable $e) { $event->increment('attempts'); $event->update(['last_error'=>substr($e->getMessage(),0,65000)]); }
        });
        return self::SUCCESS;
    }
}
