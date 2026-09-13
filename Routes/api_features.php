<?php

declare(strict_types=1);

// Canonical P0-P51 feature routes. Loaded inside /api/v1 by PriyasaCoreServiceProvider.

// routes_p21.patch

Route::middleware(['auth:sanctum','priyasa.admin','priyasa.admin.security'])->prefix('admin/finance/reports')->group(function(){
 Route::get('/tax', [\Modules\PriyasaCore\Http\Controllers\Admin\FinancialReportsController::class,'tax'])->middleware('priyasa.admin.permission:finance.view');
 Route::get('/invoices', [\Modules\PriyasaCore\Http\Controllers\Admin\FinancialReportsController::class,'invoices'])->middleware('priyasa.admin.permission:finance.view');
 Route::get('/refunds', [\Modules\PriyasaCore\Http\Controllers\Admin\FinancialReportsController::class,'refunds'])->middleware('priyasa.admin.permission:finance.view');
 Route::get('/wallet', [\Modules\PriyasaCore\Http\Controllers\Admin\FinancialReportsController::class,'wallet'])->middleware('priyasa.admin.permission:finance.view');
 Route::get('/loyalty', [\Modules\PriyasaCore\Http\Controllers\Admin\FinancialReportsController::class,'loyalty'])->middleware('priyasa.admin.permission:finance.view');
 Route::get('/closing', [\Modules\PriyasaCore\Http\Controllers\Admin\FinancialReportsController::class,'closing'])->middleware('priyasa.admin.permission:finance.view');
});

// routes_p22.patch

Route::middleware(['auth:sanctum','priyasa.admin','priyasa.admin.security'])->prefix('admin/ai')->group(function(){
 Route::post('/intent', [\Modules\PriyasaCore\Http\Controllers\Admin\CommerceAIController::class,'intent'])->middleware('priyasa.admin.permission:analytics.view');
 Route::post('/product-content', [\Modules\PriyasaCore\Http\Controllers\Admin\CommerceAIController::class,'productContent'])->middleware('priyasa.admin.permission:catalog.manage');
});

// routes_p23.patch

Route::post('/storefront/commerce-agent/message',[\Modules\PriyasaCore\Http\Controllers\Storefront\CommerceAgentController::class,'message'])->middleware('auth:sanctum');
Route::middleware(['auth:sanctum','priyasa.admin','priyasa.admin.security'])->prefix('admin/commerce-agent')->group(function(){
 Route::get('/conversations',[\Modules\PriyasaCore\Http\Controllers\Admin\CommerceAgentController::class,'conversations'])->middleware('priyasa.admin.permission:analytics.view');
 Route::get('/conversations/{conversation}/messages',[\Modules\PriyasaCore\Http\Controllers\Admin\CommerceAgentController::class,'messages'])->middleware('priyasa.admin.permission:analytics.view');
 Route::post('/conversations/{conversation}/handoff',[\Modules\PriyasaCore\Http\Controllers\Admin\CommerceAgentController::class,'handoff'])->middleware('priyasa.admin.permission:orders.manage');
});

// routes_p25.patch

Route::post('/webhooks/meta/whatsapp', [\Modules\PriyasaCore\Http\Controllers\Webhooks\MetaWhatsAppController::class,'receive']);
Route::get('/webhooks/meta/whatsapp/verify', [\Modules\PriyasaCore\Http\Controllers\Webhooks\MetaWhatsAppController::class,'verify']);
Route::post('/storefront/whatsapp/send-interactive', [\Modules\PriyasaCore\Http\Controllers\Storefront\WhatsAppCommerceController::class,'sendInteractive'])->middleware('auth:sanctum');

// routes_p26.patch

// P26: mount inside the module's existing /api/v1 route group.
Route::get('/storefront/bootstrap', [\Modules\PriyasaCore\Http\Controllers\Storefront\ApiContractController::class,'bootstrap']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/storefront/account/me', [\Modules\PriyasaCore\Http\Controllers\Storefront\ApiContractController::class,'me']);
});

// routes_p27.patch

// P27: add inside the existing /api/v1 route group.
Route::middleware('auth:sanctum')->prefix('storefront/session')->group(function () {
    Route::get('/', [\Modules\PriyasaCore\Http\Controllers\Storefront\AuthSessionController::class,'show']);
    Route::post('/rotate', [\Modules\PriyasaCore\Http\Controllers\Storefront\AuthSessionController::class,'rotate']);
    Route::post('/logout', [\Modules\PriyasaCore\Http\Controllers\Storefront\AuthSessionController::class,'logout']);
    Route::post('/logout-all', [\Modules\PriyasaCore\Http\Controllers\Storefront\AuthSessionController::class,'logoutAll']);
});
// Register middleware aliases in bootstrap/app.php or the application's HTTP kernel:
// 'priyasa.api.contract' => ApiContractHeaders::class
// 'priyasa.auth.rate'    => AuthRateLimit::class
// Apply priyasa.api.contract to /api/v1 globally. Apply priyasa.auth.rate:otp_send,
// :otp_verify and :otp_resend to the corresponding existing P0 OTP routes.

// routes_p28.patch

// P28: mount inside the module's existing /api/v1 route group.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/storefront/notifications/devices', [\Modules\PriyasaCore\Http\Controllers\Storefront\NotificationPlatformController::class,'register']);
    Route::delete('/storefront/notifications/devices', [\Modules\PriyasaCore\Http\Controllers\Storefront\NotificationPlatformController::class,'unregister']);
    Route::get('/storefront/notifications/inbox', [\Modules\PriyasaCore\Http\Controllers\Storefront\NotificationPlatformController::class,'inbox']);
    Route::get('/storefront/notifications/unread', [\Modules\PriyasaCore\Http\Controllers\Storefront\NotificationPlatformController::class,'unread']);
    Route::post('/storefront/notifications/{id}/read', [\Modules\PriyasaCore\Http\Controllers\Storefront\NotificationPlatformController::class,'read']);
});

// routes_p29.patch

// P29: mount inside the module's existing /api/v1 route group.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/storefront/notifications/preferences', [\Modules\PriyasaCore\Http\Controllers\Storefront\NotificationPreferenceController::class,'index']);
    Route::put('/storefront/notifications/preferences', [\Modules\PriyasaCore\Http\Controllers\Storefront\NotificationPreferenceController::class,'update']);
});

// routes_p30.patch

// P30: replace the existing storefront checkout create-order route with the
// idempotent version below. Keep the existing URI if your route file already
// wraps these routes in auth:sanctum middleware.
Route::post('/storefront/checkout/create-order', [\Modules\PriyasaCore\Http\Controllers\Storefront\CheckoutController::class, 'createOrder'])
    ->middleware('idempotency');

// Existing operations route: expire checkout reservations from a scheduler/admin worker.
Route::post('/admin/ops/inventory-reservations/expire', [\Modules\PriyasaCore\Http\Controllers\Admin\OperationsReliabilityController::class, 'expireReservations'])
    ->middleware(['auth:sanctum', 'priyasa.admin', 'idempotency']);

// routes_p31.patch

// P31 payment gateway routes. Apply inside the existing API v1 route group.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders/{order}/payment/capture', [\Modules\PriyasaCore\Http\Controllers\Storefront\PaymentController::class, 'capture'])->middleware('idempotency');
});

Route::post('/webhooks/razorpay', \Modules\PriyasaCore\Http\Controllers\Webhooks\RazorpayWebhookController::class);


// routes_p32.patch
# Apply inside the authenticated admin API group.
Route::get('/admin/inventory/warehouses', [\Modules\PriyasaCore\Http\Controllers\Admin\WarehouseInventoryController::class, 'warehouses']);
Route::get('/admin/inventory/warehouse-stock', [\Modules\PriyasaCore\Http\Controllers\Admin\WarehouseInventoryController::class, 'inventory']);
Route::post('/admin/inventory/warehouse-adjust', [\Modules\PriyasaCore\Http\Controllers\Admin\WarehouseInventoryController::class, 'adjust']);

// routes_p33.patch
# Apply inside the authenticated admin API group.
Route::post('/admin/orders/{order}/fulfillment/allocate', [\Modules\PriyasaCore\Http\Controllers\Admin\FulfillmentAllocationController::class, 'allocate']);
Route::get('/admin/orders/{order}/fulfillment', [\Modules\PriyasaCore\Http\Controllers\Admin\FulfillmentAllocationController::class, 'show']);
Route::post('/admin/fulfillment/allocations/{allocation}/pick', [\Modules\PriyasaCore\Http\Controllers\Admin\FulfillmentAllocationController::class, 'pick']);
Route::post('/admin/fulfillment/allocations/{allocation}/pack', [\Modules\PriyasaCore\Http\Controllers\Admin\FulfillmentAllocationController::class, 'pack']);
Route::post('/admin/fulfillment/allocations/{allocation}/cancel', [\Modules\PriyasaCore\Http\Controllers\Admin\FulfillmentAllocationController::class, 'cancel']);
Route::post('/admin/fulfillment/transfers', [\Modules\PriyasaCore\Http\Controllers\Admin\FulfillmentAllocationController::class, 'transfer']);
Route::get('/admin/fulfillment/transfers', [\Modules\PriyasaCore\Http\Controllers\Admin\FulfillmentAllocationController::class, 'transfers']);
Route::post('/admin/fulfillment/transfers/{transfer}/receive', [\Modules\PriyasaCore\Http\Controllers\Admin\FulfillmentAllocationController::class, 'receiveTransfer']);

// routes_p34.patch
# Apply inside the authenticated admin API group.
Route::post('/admin/orders/{order}/shipping/create', [\Modules\PriyasaCore\Http\Controllers\Admin\ShippingOrchestrationController::class, 'create']);
Route::get('/admin/orders/{order}/shipping', [\Modules\PriyasaCore\Http\Controllers\Admin\ShippingOrchestrationController::class, 'show']);
Route::post('/admin/shipping/{shipment}/label', [\Modules\PriyasaCore\Http\Controllers\Admin\ShippingOrchestrationController::class, 'label']);
Route::post('/admin/shipping/{shipment}/pickup', [\Modules\PriyasaCore\Http\Controllers\Admin\ShippingOrchestrationController::class, 'pickup']);
Route::post('/admin/shipping/{shipment}/sync', [\Modules\PriyasaCore\Http\Controllers\Admin\ShippingOrchestrationController::class, 'sync']);
Route::post('/admin/shipping/{shipment}/cancel', [\Modules\PriyasaCore\Http\Controllers\Admin\ShippingOrchestrationController::class, 'cancel']);

# Configure this endpoint in the shipping provider webhook configuration.
Route::post('/webhooks/shiprocket', [\Modules\PriyasaCore\Http\Controllers\Webhooks\ShiprocketWebhookController::class, 'handle']);

// routes_p35.patch
# Apply inside the authenticated admin API group.
Route::post('/admin/returns/{return}/reverse-shipment', [\Modules\PriyasaCore\Http\Controllers\Admin\ReverseLogisticsController::class, 'createReturnShipment']);
Route::post('/admin/reverse-shipments/{shipment}/status', [\Modules\PriyasaCore\Http\Controllers\Admin\ReverseLogisticsController::class, 'reverseStatus']);
Route::post('/admin/shipments/{shipment}/ndr', [\Modules\PriyasaCore\Http\Controllers\Admin\ReverseLogisticsController::class, 'ndr']);
Route::post('/admin/ndr/{case}/resolve', [\Modules\PriyasaCore\Http\Controllers\Admin\ReverseLogisticsController::class, 'resolveNdr']);
Route::post('/admin/returns/{return}/qc', [\Modules\PriyasaCore\Http\Controllers\Admin\ReverseLogisticsController::class, 'qc']);
Route::post('/admin/returns/{return}/exchange', [\Modules\PriyasaCore\Http\Controllers\Admin\ReverseLogisticsController::class, 'exchange']);

// routes_p36.patch
# P36 Catalog + Product Experience 2.0
Route::get('/storefront/products/{product}', [\Modules\PriyasaCore\Http\Controllers\Storefront\ProductExperienceController::class,'show']);
Route::get('/storefront/variants/{variant}/availability', [\Modules\PriyasaCore\Http\Controllers\Storefront\ProductExperienceController::class,'availability']);

// routes_p37.patch
# P37 Search + Discovery 2.0
Route::get('/storefront/search', [\Modules\PriyasaCore\Http\Controllers\Storefront\SearchDiscoveryController::class,'search']);
Route::get('/storefront/search/suggestions', [\Modules\PriyasaCore\Http\Controllers\Storefront\SearchDiscoveryController::class,'suggestions']);
Route::get('/storefront/search/trending', [\Modules\PriyasaCore\Http\Controllers\Storefront\SearchDiscoveryController::class,'trending']);

// routes_p38.patch
Route::prefix('storefront')->group(function () {
    Route::get('/recommendations/home', [\Modules\PriyasaCore\Http\Controllers\Storefront\PersonalizedDiscoveryController::class, 'home']);
    Route::get('/recommendations', [\Modules\PriyasaCore\Http\Controllers\Storefront\PersonalizedDiscoveryController::class, 'recommendations']);
});

// routes_p39.patch
Route::prefix('storefront')->group(function () {
    Route::get('/cart/experience', [\Modules\PriyasaCore\Http\Controllers\Storefront\CartExperienceController::class, 'show']);
    Route::post('/cart/items', [\Modules\PriyasaCore\Http\Controllers\Storefront\CartExperienceController::class, 'add']);
    Route::patch('/cart/items/{item}', [\Modules\PriyasaCore\Http\Controllers\Storefront\CartExperienceController::class, 'update']);
    Route::delete('/cart/items/{item}', [\Modules\PriyasaCore\Http\Controllers\Storefront\CartExperienceController::class, 'remove']);
    Route::post('/cart/merge', [\Modules\PriyasaCore\Http\Controllers\Storefront\CartExperienceController::class, 'merge']);
});

// routes_p40.patch
Route::prefix('storefront/checkout')->group(function () {
    Route::get('/addresses', [\Modules\PriyasaCore\Http\Controllers\Storefront\CheckoutExperienceController::class, 'addresses']);
    Route::post('/addresses', [\Modules\PriyasaCore\Http\Controllers\Storefront\CheckoutExperienceController::class, 'createAddress']);
    Route::patch('/addresses/{address}', [\Modules\PriyasaCore\Http\Controllers\Storefront\CheckoutExperienceController::class, 'updateAddress']);
    Route::delete('/addresses/{address}', [\Modules\PriyasaCore\Http\Controllers\Storefront\CheckoutExperienceController::class, 'deleteAddress']);
    Route::post('/addresses/{address}/default', [\Modules\PriyasaCore\Http\Controllers\Storefront\CheckoutExperienceController::class, 'setDefault']);
    Route::post('/delivery', [\Modules\PriyasaCore\Http\Controllers\Storefront\CheckoutExperienceController::class, 'delivery']);
    Route::post('/quote', [\Modules\PriyasaCore\Http\Controllers\Storefront\CheckoutExperienceController::class, 'quote']);
    Route::post('/place', [\Modules\PriyasaCore\Http\Controllers\Storefront\CheckoutExperienceController::class, 'place']);
});

// routes_p41.patch
Route::prefix('storefront/account')->group(function () {
    Route::get('/me', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerAccountExperienceController::class, 'me']);
    Route::get('/dashboard', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerAccountExperienceController::class, 'dashboard']);
    Route::get('/orders', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerAccountExperienceController::class, 'orders']);
    Route::get('/orders/{order}', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerAccountExperienceController::class, 'order']);
    Route::get('/security', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerAccountExperienceController::class, 'security']);
});

// routes_p42.patch
Route::prefix('storefront')->group(function () {
    Route::get('/products/{product}/reviews', [\Modules\PriyasaCore\Http\Controllers\Storefront\ReviewController::class, 'index']);
    Route::post('/reviews', [\Modules\PriyasaCore\Http\Controllers\Storefront\ReviewController::class, 'store'])->middleware('auth:sanctum');
    Route::post('/reviews/{review}/vote', [\Modules\PriyasaCore\Http\Controllers\Storefront\ReviewController::class, 'vote'])->middleware('auth:sanctum');
    Route::get('/account/reviews', [\Modules\PriyasaCore\Http\Controllers\Storefront\ReviewController::class, 'mine'])->middleware('auth:sanctum');
});
Route::middleware(['auth:sanctum','priyasa.admin'])->prefix('admin/reviews')->group(function () {
    Route::get('/', [\Modules\PriyasaCore\Http\Controllers\Admin\ReviewModerationController::class, 'index']);
    Route::post('/{review}/moderate', [\Modules\PriyasaCore\Http\Controllers\Admin\ReviewModerationController::class, 'moderate']);
});

// routes_p43.patch
Route::prefix('storefront')->group(function () {
    Route::get('/wishlist', [\Modules\PriyasaCore\Http\Controllers\Storefront\WishlistEngagementController::class, 'index']);
    Route::post('/wishlist/items', [\Modules\PriyasaCore\Http\Controllers\Storefront\WishlistEngagementController::class, 'add']);
    Route::delete('/wishlist/items/{item}', [\Modules\PriyasaCore\Http\Controllers\Storefront\WishlistEngagementController::class, 'remove']);
    Route::post('/wishlist/merge', [\Modules\PriyasaCore\Http\Controllers\Storefront\WishlistEngagementController::class, 'merge'])->middleware('auth:sanctum');
    Route::put('/products/{product}/alerts', [\Modules\PriyasaCore\Http\Controllers\Storefront\WishlistEngagementController::class, 'alert']);
});

// routes_p44.patch
# P44 Promotion + Loyalty Experience
Route::prefix('storefront')->group(function () {
    Route::get('/promotions', [\Modules\PriyasaCore\Http\Controllers\Storefront\PromotionExperienceController::class, 'index']);
    Route::post('/promotions/validate', [\Modules\PriyasaCore\Http\Controllers\Storefront\PromotionExperienceController::class, 'validateCode']);
    Route::post('/promotions/best', [\Modules\PriyasaCore\Http\Controllers\Storefront\PromotionExperienceController::class, 'best']);
    Route::post('/promotions/loyalty/apply', [\Modules\PriyasaCore\Http\Controllers\Storefront\PromotionExperienceController::class, 'loyalty'])->middleware('auth:sanctum');
});
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/promotions/performance', [\Modules\PriyasaCore\Http\Controllers\Admin\PromotionExperienceController::class, 'performance']);
});

// routes_p45.patch
# P45 Customer Support / Helpdesk
Route::prefix('storefront')->middleware('auth:sanctum')->group(function () {
    Route::get('/support/categories', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerSupportController::class, 'categories']);
    Route::get('/support/tickets', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerSupportController::class, 'index']);
    Route::post('/support/tickets', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerSupportController::class, 'create']);
    Route::get('/support/tickets/{ticket}', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerSupportController::class, 'show']);
    Route::post('/support/tickets/{ticket}/messages', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerSupportController::class, 'reply']);
    Route::post('/support/tickets/{ticket}/close', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerSupportController::class, 'close']);
});
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/support/tickets', [\Modules\PriyasaCore\Http\Controllers\Admin\CustomerSupportController::class, 'index']);
    Route::get('/support/tickets/{ticket}', [\Modules\PriyasaCore\Http\Controllers\Admin\CustomerSupportController::class, 'show']);
    Route::patch('/support/tickets/{ticket}', [\Modules\PriyasaCore\Http\Controllers\Admin\CustomerSupportController::class, 'update']);
    Route::post('/support/tickets/{ticket}/messages', [\Modules\PriyasaCore\Http\Controllers\Admin\CustomerSupportController::class, 'reply']);
});

// routes_p46.patch
# P46 Customer Service + Returns/Refund Command Center
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/support/command-center', [\Modules\PriyasaCore\Http\Controllers\Admin\SupportCommandCenterController::class, 'dashboard']);
    Route::get('/support/orders/{order}/command-center', [\Modules\PriyasaCore\Http\Controllers\Admin\SupportCommandCenterController::class, 'order']);
    Route::get('/support/tickets/{ticket}/command-center', [\Modules\PriyasaCore\Http\Controllers\Admin\SupportCommandCenterController::class, 'ticket']);
    Route::post('/support/tickets/{ticket}/links', [\Modules\PriyasaCore\Http\Controllers\Admin\SupportCommandCenterController::class, 'link']);
    Route::post('/support/tickets/{ticket}/resolve', [\Modules\PriyasaCore\Http\Controllers\Admin\SupportCommandCenterController::class, 'resolve']);
    Route::post('/support/tickets/{ticket}/reopen', [\Modules\PriyasaCore\Http\Controllers\Admin\SupportCommandCenterController::class, 'reopen']);
    Route::post('/support/tickets/{ticket}/notify-customer', [\Modules\PriyasaCore\Http\Controllers\Admin\SupportCommandCenterController::class, 'notify']);
});

// routes_p47.patch

// P47 Customer 360 / CRM. Mount inside the application's /api/v1 route group.
Route::middleware(['auth:sanctum','priyasa.admin'])->prefix('admin/customer-360')->group(function () {
    Route::get('/', [\Modules\PriyasaCore\Http\Controllers\Admin\Customer360Controller::class,'dashboard']);
    // Static merge routes must precede /{customer} to avoid route-parameter capture.
    Route::post('/merge/preview', [\Modules\PriyasaCore\Http\Controllers\Admin\Customer360Controller::class,'mergePreview']);
    Route::post('/merge', [\Modules\PriyasaCore\Http\Controllers\Admin\Customer360Controller::class,'merge']);
    Route::get('/{customer}', [\Modules\PriyasaCore\Http\Controllers\Admin\Customer360Controller::class,'profile']);
    Route::post('/{customer}/tags', [\Modules\PriyasaCore\Http\Controllers\Admin\Customer360Controller::class,'tag']);
    Route::delete('/{customer}/tags/{tag}', [\Modules\PriyasaCore\Http\Controllers\Admin\Customer360Controller::class,'removeTag']);
    Route::post('/{customer}/notes', [\Modules\PriyasaCore\Http\Controllers\Admin\Customer360Controller::class,'note']);
    Route::put('/{customer}/consents', [\Modules\PriyasaCore\Http\Controllers\Admin\Customer360Controller::class,'consent']);
    Route::post('/{customer}/segments', [\Modules\PriyasaCore\Http\Controllers\Admin\Customer360Controller::class,'segment']);
});

// routes_p48.patch

// P48 API platform hardening. Mount this inside the application's canonical /api/v1 group.
// Apply ApiContractResponse once around the complete API route tree, after ApiContractHeaders.
// Example host application:
// Route::prefix('')->middleware(['api', ApiContractHeaders::class, ApiContractResponse::class])->group(function () {
//     // include/mount routes_p*.patch here
// });

Route::get('/docs/openapi.routes.json', function () {
    $path = base_path('docs/openapi.routes.json');
    abort_unless(is_file($path), 404);
    return response()->file($path, ['Content-Type' => 'application/json']);
});

// routes_p49.patch

// P49 — authoritative checkout transaction API. Mount inside the canonical /api/v1 group.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/storefront/checkout/transaction/quote', [\Modules\PriyasaCore\Http\Controllers\Storefront\CheckoutTransactionController::class, 'quote']);
    Route::post('/storefront/checkout/transaction/place', [\Modules\PriyasaCore\Http\Controllers\Storefront\CheckoutTransactionController::class, 'place']);
});

// routes_p50.patch

// P50 unified Admin Commerce Control Center. Mount inside the canonical /api/v1 group.
Route::middleware(['auth:sanctum','priyasa.admin'])->prefix('admin/control-center')->group(function () {
    Route::get('/', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminCommerceControlCenterController::class,'overview']);
    Route::get('/orders', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminCommerceControlCenterController::class,'orders']);
    Route::get('/inventory', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminCommerceControlCenterController::class,'inventory']);
    Route::get('/operations', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminCommerceControlCenterController::class,'operations']);
});

// routes_p51.patch

// P51 Admin Commerce Actions. Mount inside the application's canonical /api/v1 group.
Route::middleware(['auth:sanctum','priyasa.admin','priyasa.admin.security'])->prefix('admin/actions')->group(function () {
    Route::post('/orders/{order}/transition', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminCommerceActionsController::class,'orderTransition'])->middleware('\Modules\PriyasaCore\Http\Middleware\AdminPermissionV2:orders.manage');
    Route::post('/inventory/adjust', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminCommerceActionsController::class,'inventoryAdjust'])->middleware('\Modules\PriyasaCore\Http\Middleware\AdminPermissionV2:inventory.manage');
    Route::post('/warehouses/transfer', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminCommerceActionsController::class,'transfer'])->middleware('\Modules\PriyasaCore\Http\Middleware\AdminPermissionV2:inventory.manage');
    Route::post('/fulfillment/{allocation}', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminCommerceActionsController::class,'fulfillment'])->middleware('\Modules\PriyasaCore\Http\Middleware\AdminPermissionV2:fulfillment.manage');
    Route::post('/returns/{return}', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminCommerceActionsController::class,'returnAction'])->middleware('\Modules\PriyasaCore\Http\Middleware\AdminPermissionV2:returns.manage');
    Route::post('/refunds/{refund}', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminCommerceActionsController::class,'refundAction'])->middleware('\Modules\PriyasaCore\Http\Middleware\AdminPermissionV2:refunds.manage');
});

// P12 reliability operations.
Route::middleware(['auth:sanctum','priyasa.admin'])->prefix('admin/ops')->group(function (): void {
    Route::get('inventory-reservations', [\Modules\PriyasaCore\Http\Controllers\Admin\OperationsReliabilityController::class, 'inventoryReservations']);
    Route::post('inventory-reservations/expire', [\Modules\PriyasaCore\Http\Controllers\Admin\OperationsReliabilityController::class, 'expireReservations'])->middleware('idempotency');
});

// P15 customer order experience.
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/orders/{order}/timeline', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerOrderExperienceController::class, 'timeline']);
    Route::get('/orders/{order}/reorder', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerOrderExperienceController::class, 'reorder']);
    Route::get('/storefront/order-reasons', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerOrderExperienceController::class, 'reasons']);
});

// P16 customer growth read APIs.
Route::middleware('auth:sanctum')->prefix('storefront/account')->group(function (): void {
    Route::get('/growth', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerGrowthController::class, 'account']);
    Route::get('/wallet', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerGrowthController::class, 'wallet']);
    Route::get('/loyalty', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerGrowthController::class, 'loyalty']);
    Route::get('/referral-code', [\Modules\PriyasaCore\Http\Controllers\Storefront\CustomerGrowthController::class, 'referralCode']);
});

// P17 control-center compatibility endpoints. P50 owns orders/inventory/operations.
Route::middleware(['auth:sanctum','priyasa.admin'])->prefix('admin/control-center')->group(function (): void {
    Route::get('/dashboard', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminControlCenterController::class, 'dashboard']);
    Route::get('/customers', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminControlCenterController::class, 'customers']);
    Route::get('/products', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminControlCenterController::class, 'products']);
    Route::post('/bulk/price', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminControlCenterController::class, 'bulkPrice'])->middleware('idempotency');
    Route::get('/bulk/{job}', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminControlCenterController::class, 'bulkJob']);
});

// P18 RBAC/security.
Route::middleware(['auth:sanctum','priyasa.admin','priyasa.admin.security'])->prefix('admin/security')->group(function (): void {
    Route::get('/me', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminSecurityController::class, 'me']);
    Route::get('/roles', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminSecurityController::class, 'roles']);
    Route::get('/permissions', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminSecurityController::class, 'permissions']);
    Route::get('/users', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminSecurityController::class, 'users']);
    Route::post('/users/{user}/roles/{role}', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminSecurityController::class, 'assignRole'])->middleware('priyasa.admin.permission:rbac.manage')->middleware('idempotency');
    Route::delete('/users/{user}/roles/{role}', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminSecurityController::class, 'revokeRole'])->middleware('priyasa.admin.permission:rbac.manage')->middleware('idempotency');
    Route::get('/audit', [\Modules\PriyasaCore\Http\Controllers\Admin\AdminSecurityController::class, 'audit'])->middleware('priyasa.admin.permission:audit.view');
});

// P19 analytics detail APIs.
Route::middleware(['auth:sanctum','priyasa.admin','priyasa.admin.security'])->prefix('admin/analytics')->group(function (): void {
    Route::get('/overview', [\Modules\PriyasaCore\Http\Controllers\Admin\AnalyticsController::class, 'overview'])->middleware('priyasa.admin.permission:analytics.view');
    Route::get('/daily', [\Modules\PriyasaCore\Http\Controllers\Admin\AnalyticsController::class, 'daily'])->middleware('priyasa.admin.permission:analytics.view');
    Route::get('/products', [\Modules\PriyasaCore\Http\Controllers\Admin\AnalyticsController::class, 'products'])->middleware('priyasa.admin.permission:analytics.view');
    Route::post('/rebuild-daily', [\Modules\PriyasaCore\Http\Controllers\Admin\AnalyticsController::class, 'rebuild'])->middleware('priyasa.admin.permission:analytics.manage')->middleware('idempotency');
});
