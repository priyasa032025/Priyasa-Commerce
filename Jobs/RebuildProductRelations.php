<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\PriyasaCore\Services\SearchIntelligenceService;
final class RebuildProductRelations implements ShouldQueue { use Dispatchable,InteractsWithQueue,Queueable,SerializesModels; public function handle(SearchIntelligenceService $service): void {$service->rebuildRelations();} }
