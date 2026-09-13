<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\PriyasaCore\Services\NotificationAutomationService;
final class ProcessNotificationOutbox implements ShouldQueue { public function __construct(public int $limit=50){} public function handle(NotificationAutomationService $svc):void{$svc->dispatchPending($this->limit);} }
