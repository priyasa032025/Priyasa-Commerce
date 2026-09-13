<?php
namespace Modules\PriyasaCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\PriyasaCore\Services\AutomationRuleService;

final class RunAutomationRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public string $event, public array $payload = []) {}
    public function handle(AutomationRuleService $service): void { $service->run($this->event, $this->payload); }
}
