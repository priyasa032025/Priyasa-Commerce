<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Jobs;
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Bus\Dispatchable; use Illuminate\Queue\InteractsWithQueue; use Illuminate\Queue\SerializesModels; use Modules\PriyasaCore\Services\WooCommerceService;
final class SyncWooCommerce implements ShouldQueue {use Dispatchable,InteractsWithQueue,Queueable,SerializesModels; public function __construct(public string $scope='all',public int $maxPages=10){} public function handle(WooCommerceService $woo):void{if(in_array($this->scope,['all','products'],true))$woo->syncProducts($this->maxPages);if(in_array($this->scope,['all','orders'],true))$woo->syncOrders($this->maxPages);}}
