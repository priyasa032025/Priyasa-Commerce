<?php
namespace Modules\PriyasaCore\Http\Controllers\Storefront;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Services\NotificationPreferenceService;
use Modules\PriyasaCore\Services\StorefrontApiContract;
use Modules\PriyasaCore\Services\OrderNotificationService;

final class NotificationPreferenceController extends Controller
{
    public function index(Request $request) {
        $user=$request->user(); $userId=$user?->id; $customerId=$user?->customer?->id;
        return app(StorefrontApiContract::class)->success('notification.preferences', ['events'=>OrderNotificationService::supportedEvents(), 'channels'=>['fcm','whatsapp','email']]);
    }
    public function update(Request $request, NotificationPreferenceService $service) {
        $data=$request->validate(['event_type'=>'required|string|max:100','channel'=>'required|in:fcm,whatsapp,email','enabled'=>'required|boolean']);
        $user=$request->user(); $row=$service->set($user?->id, $user?->customer?->id, $data['event_type'], $data['channel'], (bool)$data['enabled']);
        return app(StorefrontApiContract::class)->success('notification.preference.updated', $row->toArray());
    }
}
