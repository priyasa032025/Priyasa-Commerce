<?php
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MetaWhatsAppMessageService
{
    private function post(array $payload): array
    {
        $cfg = config('p24_whatsapp');
        if (empty($cfg['enabled']) || empty($cfg['access_token']) || empty($cfg['phone_number_id'])) {
            throw new RuntimeException('Meta WhatsApp is not configured.');
        }
        $url = 'https://graph.facebook.com/'.($cfg['graph_version'] ?: 'v23.0').'/'.$cfg['phone_number_id'].'/messages';
        $response = Http::withToken($cfg['access_token'])->acceptJson()->post($url, array_merge(['messaging_product'=>'whatsapp'], $payload));
        if (!$response->successful()) throw new RuntimeException('Meta WhatsApp send failed: '.$response->body());
        return $response->json();
    }

    public function sendInteractiveButtons(string $to, string $body, array $buttons, ?string $header=null, ?string $footer=null): array
    {
        $buttonRows = [];
        foreach (array_slice($buttons, 0, 3) as $i => $button) {
            $buttonRows[] = ['type'=>'reply','reply'=>['id'=>(string)($button['id'] ?? 'b'.$i), 'title'=>mb_substr((string)($button['title'] ?? 'Open'),0,20)]];
        }
        $interactive=['type'=>'button','body'=>['text'=>$body],'action'=>['buttons'=>$buttonRows]];
        if ($header) $interactive['header']=['type'=>'text','text'=>$header];
        if ($footer) $interactive['footer']=['text'=>$footer];
        return $this->post(['to'=>$to,'type'=>'interactive','interactive'=>$interactive]);
    }

    public function sendList(string $to, string $body, string $buttonText, array $sections, ?string $header=null, ?string $footer=null): array
    {
        $normalized=[];
        foreach (array_slice($sections,0,10) as $section) {
            $rows=[];
            foreach (array_slice($section['rows'] ?? [],0,10) as $row) {
                $rows[]=['id'=>(string)$row['id'],'title'=>mb_substr((string)$row['title'],0,24),'description'=>mb_substr((string)($row['description'] ?? ''),0,72)];
            }
            $normalized[]=['title'=>mb_substr((string)($section['title'] ?? ''),0,24),'rows'=>$rows];
        }
        $interactive=['type'=>'list','body'=>['text'=>$body],'action'=>['button'=>mb_substr($buttonText,0,20),'sections'=>$normalized]];
        if ($header) $interactive['header']=['type'=>'text','text'=>$header];
        if ($footer) $interactive['footer']=['text'=>$footer];
        return $this->post(['to'=>$to,'type'=>'interactive','interactive'=>$interactive]);
    }

    public function sendImage(string $to, string $url, ?string $caption=null): array
    {
        $image=['link'=>$url]; if ($caption) $image['caption']=$caption;
        return $this->post(['to'=>$to,'type'=>'image','image'=>$image]);
    }

    public function sendTemplate(string $to, string $name, string $language='en', array $components=[]): array
    {
        return $this->post(['to'=>$to,'type'=>'template','template'=>['name'=>$name,'language'=>['code'=>$language],'components'=>$components]]);
    }

    public function sendProduct(string $to, string $catalogId, string $productRetailerId, ?string $body=null, ?string $footer=null): array
    {
        $interactive=['type'=>'product','body'=>['text'=>$body ?: 'Product details'],'action'=>['catalog_id'=>$catalogId,'product_retailer_id'=>$productRetailerId]];
        if ($footer) $interactive['footer']=['text'=>$footer];
        return $this->post(['to'=>$to,'type'=>'interactive','interactive'=>$interactive]);
    }
}
