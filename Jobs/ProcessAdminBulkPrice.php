<?php
namespace Modules\PriyasaCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProcessAdminBulkPrice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public function __construct(private readonly int $jobId, private readonly array $payload) {}
    public function handle(): void
    {
        $job=DB::connection('priyasa')->table('priyasa_admin_bulk_jobs')->where('id',$this->jobId)->first();
        if (!$job || $job->status==='completed') return;
        DB::connection('priyasa')->table('priyasa_admin_bulk_jobs')->where('id',$this->jobId)->update(['status'=>'processing','updated_at'=>now()]);
        $processed=0;$failed=0;
        foreach (array_chunk($this->payload['variant_ids'],50) as $ids) {
            foreach ($ids as $id) {
                try {
                    DB::connection('priyasa')->transaction(function() use ($id) {
                        $v=DB::connection('priyasa')->table('priyasa_product_variants')->where('id',$id)->lockForUpdate()->first();
                        if (!$v) throw new \RuntimeException('Variant not found: '.$id);
                        $old=(float)($v->price ?? 0); $amount=(float)$this->payload['amount']; $mode=$this->payload['mode'];
                        $new=$mode==='set'?$amount:($mode==='increase'?$old+$amount:max(0,$old-$amount));
                        DB::connection('priyasa')->table('priyasa_product_variants')->where('id',$id)->update(['price'=>$new,'updated_at'=>now()]);
                        if (DB::connection('priyasa')->getSchemaBuilder()->hasTable('priyasa_price_change_logs')) DB::connection('priyasa')->table('priyasa_price_change_logs')->insert(['product_id'=>$v->product_id,'variant_id'=>$id,'field'=>'price','old_value'=>$old,'new_value'=>$new,'operation'=>$mode,'operation_value'=>$amount,'actor_user_id'=>DB::connection('priyasa')->table('priyasa_admin_bulk_jobs')->where('id',$this->jobId)->value('created_by'),'source'=>'admin','reason'=>'admin_bulk_price','created_at'=>now(),'updated_at'=>now()]);
                    });
                    $processed++;
                } catch (Throwable $e) { $failed++; }
            }
            DB::connection('priyasa')->table('priyasa_admin_bulk_jobs')->where('id',$this->jobId)->update(['processed'=>$processed,'failed'=>$failed,'updated_at'=>now()]);
        }
        DB::connection('priyasa')->table('priyasa_admin_bulk_jobs')->where('id',$this->jobId)->update(['status'=>$failed?'completed_with_errors':'completed','updated_at'=>now()]);
    }
}
