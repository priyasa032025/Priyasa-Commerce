<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
final class AuditLogService {
 public function record(string $action, ?string $entityType=null, $entityId=null, ?array $before=null, ?array $after=null, array $metadata=[]): void {
  DB::connection('priyasa')->table('priyasa_audit_logs')->insert(['user_id'=>optional(Request::user())->id,'action'=>$action,'entity_type'=>$entityType,'entity_id'=>$entityId===null?null:(string)$entityId,'request_id'=>Request::header('X-Request-ID'),'ip_address'=>Request::ip(),'user_agent'=>substr((string)Request::userAgent(),0,500),'before'=>$before?json_encode($before):null,'after'=>$after?json_encode($after):null,'metadata'=>$metadata?json_encode($metadata):null,'created_at'=>now(),'updated_at'=>now()]);
 }
}
