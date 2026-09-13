<?php
namespace Modules\PriyasaCore\Jobs;
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Bus\Dispatchable; use Illuminate\Queue\InteractsWithQueue; use Illuminate\Queue\SerializesModels;
use Modules\PriyasaCore\Services\CommerceAgentService;
class ProcessCommerceAgentMessage implements ShouldQueue {use Dispatchable,InteractsWithQueue,Queueable,SerializesModels; public function __construct(public string $channel,public string $externalId,public string $text,public ?int $customerId=null){} public function handle(CommerceAgentService $agent): void {$agent->handle($this->channel,$this->externalId,$this->text,$this->customerId);}}
