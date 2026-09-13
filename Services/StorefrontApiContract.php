<?php
namespace Modules\PriyasaCore\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

final class StorefrontApiContract
{
    public function success(string $type, mixed $data, array $meta = [], int $status = 200): array
    {
        return ['success'=>true,'type'=>$type,'data'=>$this->normalize($data),'meta'=>$meta,'errors'=>[],'api_version'=>'v1'];
    }

    public function error(string $code, string $message, array $details = [], int $status = 400): array
    {
        return ['success'=>false,'type'=>'error','data'=>null,'meta'=>[],'errors'=>[['code'=>$code,'message'=>$message,'details'=>$details]],'api_version'=>'v1','http_status'=>$status];
    }

    public function paginated(LengthAwarePaginator $p, string $type): array
    {
        return $this->success($type, $p->items(), [
            'pagination'=>[
                'current_page'=>$p->currentPage(), 'per_page'=>$p->perPage(),
                'total'=>$p->total(), 'last_page'=>$p->lastPage(),
                'has_more'=>$p->hasMorePages(),
            ],
        ]);
    }

    public function normalize(mixed $value): mixed
    {
        if ($value instanceof LengthAwarePaginator) return $value->items();
        if (is_object($value) && method_exists($value,'toArray')) return $value->toArray();
        if (is_array($value)) return array_map(fn($v)=>$this->normalize($v), $value);
        return $value;
    }
}
