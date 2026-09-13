<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services;
use Illuminate\Support\Facades\DB; use Modules\PriyasaCore\Models\CmsSection; use Modules\PriyasaCore\Models\CmsVersion;
final class CmsPublishingService {
 public function snapshot(string $page='home'): array { return CmsSection::query()->where('is_active',true)->orderBy('sort_order')->orderBy('id')->get()->map(fn($s)=>$s->toArray())->values()->all(); }
 public function saveDraft(string $page='home',?int $actor=null): CmsVersion { return DB::connection('priyasa')->transaction(function()use($page,$actor){$v=(int)(CmsVersion::where('page_key',$page)->max('version')??0)+1; return CmsVersion::create(['page_key'=>$page,'version'=>$v,'snapshot'=>$this->snapshot($page),'status'=>'draft','created_by'=>$actor]);}); }
 public function publish(CmsVersion $version): CmsVersion { return DB::connection('priyasa')->transaction(function()use($version){ CmsVersion::where('page_key',$version->page_key)->where('status','published')->update(['status'=>'archived']); $version->update(['status'=>'published','published_at'=>now()]); return $version->fresh(); }); }
}
