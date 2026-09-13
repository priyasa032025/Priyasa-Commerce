<?php
namespace Modules\PriyasaCore\Jobs;
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Bus\Dispatchable; use Illuminate\Queue\InteractsWithQueue; use Illuminate\Queue\SerializesModels; use Carbon\Carbon; use Modules\PriyasaCore\Services\AnalyticsService;
final class ProcessAnalyticsDaily implements ShouldQueue { use Dispatchable,InteractsWithQueue,Queueable,SerializesModels; public function __construct(public string $date){} public function handle(AnalyticsService $analytics): void { $analytics->persistDaily(Carbon::parse($this->date)); } }
