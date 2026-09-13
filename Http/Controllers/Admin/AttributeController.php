<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\PriyasaCore\Models\ProductAttribute;
use Modules\PriyasaCore\Models\ProductAttributeOption;

final class AttributeController extends Controller
{
    public function index(Request $request)
    {
        $q = ProductAttribute::query()->with(['options' => fn($x) => $x->orderBy('sort_order')->orderBy('id')])->orderBy('name');
        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn($x) => $x->where('name','like',"%{$search}%")->orWhere('slug','like',"%{$search}%"));
        }
        return response()->json(['success'=>true,'data'=>$q->paginate(min(max((int)$request->query('per_page',100),1),200))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'=>'required|string|max:255',
            'slug'=>['required','string','max:191',Rule::unique('priyasa_attributes','slug')],
            'type'=>'nullable|in:select,multiselect,text,number,color',
            'is_variation'=>'nullable|boolean','is_filterable'=>'nullable|boolean','is_active'=>'nullable|boolean',
            'source'=>'nullable|string|max:32','external_id'=>'nullable|integer',
            'options'=>'nullable|array', 'options.*.label'=>'required_with:options|string|max:255', 'options.*.slug'=>'nullable|string|max:191',
        ]);
        $options = $data['options'] ?? [];
        unset($data['options']);
        $attribute = ProductAttribute::create(array_merge(['type'=>'select','is_variation'=>false,'is_filterable'=>true,'is_active'=>true,'source'=>'manual'],$data));
        foreach ($options as $i => $option) {
            $label = trim((string)$option['label']);
            $slug = $option['slug'] ?? str()->slug($label);
            $attribute->options()->firstOrCreate(['slug'=>$slug], ['label'=>$label,'sort_order'=>$i,'is_active'=>true]);
        }
        return response()->json(['success'=>true,'data'=>$attribute->load('options')],201);
    }

    public function update(Request $request, ProductAttribute $attribute)
    {
        $data = $request->validate([
            'name'=>'sometimes|string|max:255', 'slug'=>['sometimes','string','max:191',Rule::unique('priyasa_attributes','slug')->ignore($attribute->id)],
            'type'=>'sometimes|in:select,multiselect,text,number,color','is_variation'=>'sometimes|boolean','is_filterable'=>'sometimes|boolean','is_active'=>'sometimes|boolean',
        ]);
        $attribute->update($data);
        return response()->json(['success'=>true,'data'=>$attribute->fresh()->load('options')]);
    }

    public function destroy(ProductAttribute $attribute)
    {
        $attribute->update(['is_active'=>false]);
        $attribute->options()->update(['is_active'=>false]);
        return response()->json(['success'=>true,'data'=>$attribute->fresh()->load('options')]);
    }

    public function optionStore(Request $request, ProductAttribute $attribute)
    {
        $data = $request->validate([
            'label'=>'required|string|max:255',
            'slug'=>'nullable|string|max:191',
            'sort_order'=>'nullable|integer|min:0',
            'is_active'=>'nullable|boolean',
        ]);
        $label = trim((string)$data['label']);
        $slug = $data['slug'] ?? str()->slug($label);
        $option = $attribute->options()->firstOrCreate(['slug'=>$slug], ['label'=>$label,'sort_order'=>(int)($data['sort_order']??0),'is_active'=>(bool)($data['is_active']??true)]);
        if ($option->wasRecentlyCreated === false) $option->update(array_filter(['label'=>$label,'sort_order'=>$data['sort_order']??null,'is_active'=>$data['is_active']??null], fn($v)=>$v!==null));
        return response()->json(['success'=>true,'data'=>$option->fresh()],201);
    }

    public function optionUpdate(Request $request, ProductAttribute $attribute, ProductAttributeOption $option)
    {
        abort_unless((int)$option->attribute_id === (int)$attribute->id, 404);
        $data = $request->validate(['label'=>'sometimes|string|max:255','slug'=>'sometimes|string|max:191','sort_order'=>'sometimes|integer|min:0','is_active'=>'sometimes|boolean']);
        $option->update($data);
        return response()->json(['success'=>true,'data'=>$option->fresh()]);
    }
}
