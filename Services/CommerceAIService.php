<?php
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Str;

final class CommerceAIService
{
    public function classifyIntent(string $text): array
    {
        $t = Str::lower(trim($text));
        $rules = [
            'order_status' => ['where is my order','track my order','order status','delivery status'],
            'return' => ['return','refund','exchange'],
            'product' => ['product','size','colour','color','material','available'],
            'payment' => ['payment','paid','payment failed','refund amount'],
        ];
        foreach ($rules as $intent => $phrases) foreach ($phrases as $phrase) if (Str::contains($t, $phrase)) return ['intent'=>$intent,'confidence'=>0.92];
        return ['intent'=>'general','confidence'=>0.25];
    }

    public function generateProductContent(array $product): array
    {
        $name = trim((string)($product['name'] ?? 'Product'));
        $brand = trim((string)($product['brand'] ?? ''));
        $category = trim((string)($product['category'] ?? ''));
        $description = $name . ($brand ? ' by '.$brand : '') . ($category ? ' in '.$category : '') . '.';
        return [
            'title' => $name,
            'description' => $description,
            'seo_title' => Str::limit($name.' | Priyasa', 60, ''),
            'meta_description' => Str::limit($description, 155, ''),
            'provider' => 'rule_based',
        ];
    }
}
