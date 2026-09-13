<?php
namespace Modules\PriyasaCore\Services;
use Modules\PriyasaCore\Models\NotificationPreference;

final class NotificationPreferenceService
{
    public function enabled(?int $userId, ?int $customerId, string $eventType, string $channel): bool
    {
        $q = NotificationPreference::query()->where('event_type',$eventType)->where('channel',$channel);
        if ($userId) $q->where(function($x) use($userId){$x->where('user_id',$userId)->orWhereNull('user_id');});
        elseif ($customerId) $q->where(function($x) use($customerId){$x->where('customer_id',$customerId)->orWhereNull('customer_id');});
        else return false;
        $row=$q->orderByRaw('CASE WHEN user_id IS NOT NULL THEN 0 ELSE 1 END')->first();
        return $row ? (bool)$row->enabled : true;
    }
    public function set(?int $userId, ?int $customerId, string $eventType, string $channel, bool $enabled): NotificationPreference
    {
        return NotificationPreference::updateOrCreate(['user_id'=>$userId,'event_type'=>$eventType,'channel'=>$channel], ['customer_id'=>$customerId,'enabled'=>$enabled]);
    }
}
