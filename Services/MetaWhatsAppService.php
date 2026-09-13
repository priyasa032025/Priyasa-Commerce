<?php
namespace Modules\PriyasaCore\Services;
use Illuminate\Support\Facades\Http; use RuntimeException;
final class MetaWhatsAppService {
 public function sendText(string $to,string $body): array { $cfg=config('p24_whatsapp'); if(!$cfg['enabled']||!$cfg['access_token']||!$cfg['phone_number_id']) throw new RuntimeException('Meta WhatsApp is not configured.'); $url='https://graph.facebook.com/'.$cfg['graph_version'].'/'.$cfg['phone_number_id'].'/messages'; $r=Http::withToken($cfg['access_token'])->acceptJson()->post($url,['messaging_product'=>'whatsapp','to'=>$to,'type'=>'text','text'=>['preview_url'=>false,'body'=>$body]]); if(!$r->successful()) throw new RuntimeException('Meta WhatsApp send failed: '.$r->body()); return $r->json(); }
 public function verify(string $mode,string $token,string $challenge): ?string {if($mode==='subscribe' && hash_equals((string)config('p24_whatsapp.verify_token'),$token)) return $challenge; return null;}
 public function validSignature(string $raw,string $header): bool { $secret=(string)config('p24_whatsapp.app_secret'); if($secret===''||!str_starts_with($header,'sha256=')) return false; return hash_equals('sha256='.hash_hmac('sha256',$raw,$secret),$header); }
}
