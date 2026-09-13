<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\PriyasaCore\Models\CmsSection;
use Modules\PriyasaCore\Models\CmsVersion;
use Modules\PriyasaCore\Services\CmsPublishingService;

final class CmsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => CmsSection::query()->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $section = DB::connection('priyasa')->transaction(fn () => CmsSection::create($data));
        return response()->json(['success' => true, 'data' => $section], 201);
    }

    public function update(Request $request, CmsSection $section): JsonResponse
    {
        $section->update($this->validated($request, $section));
        return response()->json(['success' => true, 'data' => $section->fresh()]);
    }

    public function destroy(CmsSection $section): JsonResponse
    {
        $section->update(['is_active' => false]);
        return response()->json(['success' => true, 'data' => $section->fresh()]);
    }


    public function preview(Request $request): JsonResponse
    {
        return response()->json(['success'=>true,'data'=>['page'=>'home','mode'=>'preview','sections'=>CmsSection::query()->orderBy('sort_order')->orderBy('id')->get()]]);
    }

    public function saveDraft(Request $request, CmsPublishingService $publisher): JsonResponse
    {
        $version=$publisher->saveDraft('home', $request->user()?->getAuthIdentifier());
        return response()->json(['success'=>true,'data'=>$version],201);
    }

    public function versions(): JsonResponse
    {
        return response()->json(['success'=>true,'data'=>CmsVersion::query()->where('page_key','home')->orderByDesc('version')->paginate(20)]);
    }

    public function publish(Request $request, CmsVersion $version, CmsPublishingService $publisher): JsonResponse
    {
        abort_unless($version->page_key==='home',404);
        return response()->json(['success'=>true,'data'=>$publisher->publish($version)]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate(['sections' => ['required', 'array', 'min:1'], 'sections.*.id' => ['required', 'integer'], 'sections.*.sort_order' => ['required', 'integer', 'min:0']]);
        DB::connection('priyasa')->transaction(function () use ($data) {
            foreach ($data['sections'] as $item) {
                CmsSection::query()->whereKey($item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });
        return response()->json(['success' => true, 'data' => CmsSection::query()->orderBy('sort_order')->orderBy('id')->get()]);
    }

    private function validated(Request $request, ?CmsSection $section = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:120', Rule::unique('priyasa_cms_sections', 'key')->ignore($section?->id)],
            'type' => ['required', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'cta_href' => ['nullable', 'string', 'max:2048'],
            'content' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }
}
