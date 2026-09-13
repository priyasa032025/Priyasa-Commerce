<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\PriyasaCore\Models\Category;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query()->withCount('products')->with('children:id,parent_id,name,slug,is_active,sort_order');
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"));
        }
        if ($request->has('active')) $query->where('is_active', $request->boolean('active'));
        return response()->json(['success'=>true,'data'=>$query->orderBy('sort_order')->orderBy('name')->paginate(min(max($request->integer('per_page',50),1),100))]);
    }

    public function show(Category $category)
    {
        return response()->json(['success'=>true,'data'=>$category->loadCount('products')->load('children')]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->validateParent($data['parent_id'] ?? null);
        $category = DB::connection('priyasa')->transaction(fn () => Category::create($data));
        return response()->json(['success'=>true,'data'=>$category->loadCount('products')], 201);
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);
        $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $category->parent_id;
        if ($parentId !== null && (int) $parentId === (int) $category->id) abort(422, 'A category cannot be its own parent.');
        $this->validateParent($parentId, $category);
        DB::connection('priyasa')->transaction(fn () => $category->update($data));
        return response()->json(['success'=>true,'data'=>$category->fresh()->loadCount('products')->load('children')]);
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) return response()->json(['success'=>false,'message'=>'Category contains products. Move the products before deactivating it.'],422);
        if ($category->children()->where('is_active', true)->exists()) return response()->json(['success'=>false,'message'=>'Category has active child categories. Deactivate or move them first.'],422);
        $category->update(['is_active'=>false]);
        return response()->json(['success'=>true,'data'=>$category->fresh()]);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name'=>'required|string|max:255',
            'slug'=>['required','string','max:255',Rule::unique('priyasa_categories','slug')->ignore($category?->id)],
            'description'=>'nullable|string',
            'image_url'=>'nullable|url|max:2048',
            'sort_order'=>'nullable|integer|min:0|max:2147483647',
            'is_active'=>'nullable|boolean',
            'parent_id'=>['nullable','integer','exists:priyasa_categories,id'],
        ]);
    }

    private function validateParent(?int $parentId, ?Category $category = null): void
    {
        if ($parentId === null || !$category) return;
        $cursor = Category::find($parentId);
        $seen = [];
        while ($cursor) {
            if (isset($seen[$cursor->id])) break;
            $seen[$cursor->id] = true;
            if ((int) $cursor->id === (int) $category->id) abort(422, 'Category hierarchy cannot contain a cycle.');
            $cursor = $cursor->parent_id ? Category::find($cursor->parent_id) : null;
        }
    }
}
