<?php
namespace Modules\PriyasaCore\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Services\CommerceAIService;

final class CommerceAIController extends Controller
{
    public function intent(Request $request, CommerceAIService $ai) { return response()->json($ai->classifyIntent($request->string('text')->toString())); }
    public function productContent(Request $request, CommerceAIService $ai) { $data=$request->validate(['product'=>['required','array']]); return response()->json($ai->generateProductContent($data['product'])); }
}
