<?php
namespace Modules\PriyasaCore\Services;
final class DeepLinkService {
    public function order($id): array { return $this->make('orders/'.rawurlencode((string)$id)); }
    public function product($id): array { return $this->make('products/'.rawurlencode((string)$id)); }
    public function returnRequest($id): array { return $this->make('returns/'.rawurlencode((string)$id)); }
    public function make(string $path): array {
        $path=ltrim($path,'/'); $scheme=(string)config('p28_notifications.deep_links.scheme','priyasa');
        $web=rtrim((string)config('p28_notifications.deep_links.web_url',''),'/').'/'.$path;
        return ['app_url'=>$scheme.'://'.$path,'web_url'=>$web,'path'=>$path];
    }
}
