<?php

use Illuminate\Support\Facades\Route;
use Modules\PriyasaCore\Http\Controllers\Api\V1\Auth\OTPController;
use Modules\PriyasaCore\Http\Controllers\Api\V1\DeviceToken\DeviceController;
use Modules\PriyasaCore\Http\Controllers\HealthController;
use Modules\PriyasaCore\Http\Controllers\IntegrationWebhookController;
use Modules\PriyasaCore\Http\Controllers\Storefront\CatalogController;
use Modules\PriyasaCore\Http\Controllers\Storefront\CartController;
use Modules\PriyasaCore\Http\Controllers\Storefront\CheckoutController;
use Modules\PriyasaCore\Http\Controllers\Storefront\OrderController;
use Modules\PriyasaCore\Http\Controllers\Storefront\WishlistController;
use Modules\PriyasaCore\Http\Controllers\Storefront\ReviewController;
use Modules\PriyasaCore\Http\Controllers\Storefront\ReturnController as StorefrontReturnController;
use Modules\PriyasaCore\Http\Controllers\Storefront\PaymentController;
use Modules\PriyasaCore\Http\Controllers\Storefront\AddressController;
use Modules\PriyasaCore\Http\Controllers\Storefront\CmsController;
use Modules\PriyasaCore\Http\Controllers\Storefront\CustomerController as StorefrontCustomerController;
use Modules\PriyasaCore\Http\Controllers\Storefront\ShippingController as StorefrontShippingController;
use Modules\PriyasaCore\Http\Controllers\Storefront\SettingsController as StorefrontSettingsController;
use Modules\PriyasaCore\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use Modules\PriyasaCore\Http\Controllers\Admin\ProductMediaController;
use Modules\PriyasaCore\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use Modules\PriyasaCore\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use Modules\PriyasaCore\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use Modules\PriyasaCore\Http\Controllers\Admin\SettingsController;
use Modules\PriyasaCore\Http\Controllers\Admin\AuditLogController;
use Modules\PriyasaCore\Http\Controllers\Admin\ShippingController;
use Modules\PriyasaCore\Http\Controllers\Admin\AnalyticsController;
use Modules\PriyasaCore\Http\Controllers\Admin\AdminUserController;
use Modules\PriyasaCore\Http\Controllers\Admin\OrderController as AdminOrderController;
use Modules\PriyasaCore\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use Modules\PriyasaCore\Http\Controllers\Admin\ReturnController as AdminReturnController;
use Modules\PriyasaCore\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use Modules\PriyasaCore\Http\Controllers\Storefront\InvoiceController as StorefrontInvoiceController;
use Modules\PriyasaCore\Http\Controllers\Admin\ReviewController as AdminReviewController;
use Modules\PriyasaCore\Http\Controllers\Admin\CollectionController as AdminCollectionController;
use Modules\PriyasaCore\Http\Controllers\AdminAuth\EmailOtpController as AdminEmailOtpController;
use Modules\PriyasaCore\Http\Controllers\Admin\CmsController as AdminCmsController;
use Modules\PriyasaCore\Http\Controllers\Admin\AttributeController as AdminAttributeController;
use Modules\PriyasaCore\Http\Controllers\Storefront\SearchController;
use Modules\PriyasaCore\Http\Controllers\Admin\MerchandisingController;
use Modules\PriyasaCore\Http\Controllers\Storefront\PersonalizationController;

Route::prefix('v1')->group(function () {
    // Unified authentication surface after Core -> PriyasaCore merge.
    Route::prefix('auth')->name('api.v1.auth.')->middleware('throttle:otp')->group(function () {
        Route::post('send-otp', [OTPController::class, 'send'])->name('send-otp');
        Route::post('verify-otp', [OTPController::class, 'verify'])->name('verify-otp');
        Route::post('resend-otp', [OTPController::class, 'resend'])->name('resend-otp');
        Route::delete('cancel-otp/{requestId}', [OTPController::class, 'cancel'])->name('cancel-otp');
    });

    Route::prefix('device')->name('api.v1.device.')->group(function () {
        Route::post('register', [DeviceController::class, 'register'])->name('register');
        Route::post('refresh', [DeviceController::class, 'refresh'])->name('refresh');
        Route::post('telemetry', [DeviceController::class, 'telemetry'])->name('telemetry');
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('update', [DeviceController::class, 'updateUser'])->name('update');
            Route::post('logout', [DeviceController::class, 'logout'])->name('logout');
            Route::delete('delete', [DeviceController::class, 'destroy'])->name('delete');
        });
        Route::get('tokens', [DeviceController::class, 'activeDevices'])->name('tokens');
        Route::get('user/{id}', [DeviceController::class, 'userDevices'])->name('user');
    });
Route::get('health', HealthController::class);
Route::post('webhooks/{provider}', [IntegrationWebhookController::class, 'handle'])->where('provider','[A-Za-z0-9_-]+');

Route::prefix('admin/auth')->group(function () {
    Route::post('send-otp', [AdminEmailOtpController::class, 'send']);
    Route::post('verify-otp', [AdminEmailOtpController::class, 'verify']);
});

Route::prefix('storefront')->group(function () {
    Route::get('home', [CmsController::class, 'home']);
    Route::get('cms/{key}', [CmsController::class, 'show']);
    // Compatibility endpoint for older storefront deployments.
    Route::get('promo', function (\Illuminate\Http\Request $request) {
        $code = trim((string) $request->query('code', ''));
        if ($code === '') return response()->json(['data' => null]);
        return response()->json(['data' => ['code' => strtoupper($code), 'valid' => false, 'message' => 'Promo validation is performed during checkout.']]);
    });
    Route::get('products', [CatalogController::class, 'products']);
    Route::get('search', [SearchController::class, 'search']);
    Route::get('search/suggestions', [SearchController::class, 'suggestions']);
    Route::get('recommendations', [PersonalizationController::class, 'recommendations']);
    Route::get('recently-viewed', [PersonalizationController::class, 'recentlyViewed']);
    Route::post('events', [PersonalizationController::class, 'track']);
    Route::get('products/{product}', [CatalogController::class, 'show']);
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('collections', [CatalogController::class, 'collections']);
    Route::get('collections/{collection}', [CatalogController::class, 'collection']);
    Route::get('settings', [StorefrontSettingsController::class, 'index']);
    Route::get('shipping/serviceability', [StorefrontShippingController::class, 'serviceability']);
    Route::get('products/{product}/reviews', [ReviewController::class, 'index']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [StorefrontCustomerController::class, 'me']);
        Route::patch('me', [StorefrontCustomerController::class, 'update']);
        Route::get('addresses', [AddressController::class, 'index']);
        Route::post('addresses', [AddressController::class, 'store']);
        Route::put('addresses/{address}', [AddressController::class, 'update']);
        Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
        Route::get('cart', [CartController::class, 'show']);
        Route::post('cart/items', [CartController::class, 'add']);
        Route::patch('cart/items/{item}', [CartController::class, 'update']);
        Route::delete('cart/items/{item}', [CartController::class, 'remove']);
        Route::post('checkout/validate', [CheckoutController::class, 'validateCart']);
        Route::post('checkout/create-order', [CheckoutController::class, 'createOrder'])->middleware('idempotency');
        Route::post('orders/{order}/payment', [PaymentController::class, 'create'])->middleware('idempotency');
        Route::post('orders/{order}/payment/capture', [PaymentController::class, 'capture'])->middleware('idempotency');
        Route::get('orders/{order}/payment', [PaymentController::class, 'status']);
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::get('orders/{order}/tracking', [OrderController::class, 'tracking']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('idempotency');
        Route::get('wishlist', [WishlistController::class, 'index']);
        Route::post('wishlist/{variant}/toggle', [WishlistController::class, 'toggle']);
        Route::post('products/{product}/reviews', [ReviewController::class, 'store']);
        Route::get('returns', [StorefrontReturnController::class, 'index']);
        Route::post('orders/{order}/returns', [StorefrontReturnController::class, 'store']);
        Route::get('returns/{return}', [StorefrontReturnController::class, 'show']);
        Route::get('orders/{order}/invoice', [StorefrontInvoiceController::class, 'show']);
    });
});

// Backward-compatible legacy device endpoints. New clients should use /api/v1/device/*.
Route::prefix('device')->group(function () {
    Route::post('register', [DeviceController::class, 'register']);
    Route::post('refresh', [DeviceController::class, 'refresh']);
    Route::post('telemetry', [DeviceController::class, 'telemetry']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('update', [DeviceController::class, 'updateUser']);
        Route::post('logout', [DeviceController::class, 'logout']);
        Route::delete('delete', [DeviceController::class, 'destroy']);
    });
    Route::get('tokens', [DeviceController::class, 'activeDevices']);
    Route::get('user/{id}', [DeviceController::class, 'userDevices']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'priyasa.admin'])->group(function () {
    Route::get('integrations', [\Modules\PriyasaCore\Http\Controllers\Admin\IntegrationController::class, 'index']);
    Route::get('integrations/woocommerce/status', [\Modules\PriyasaCore\Http\Controllers\Admin\WooCommerceController::class, 'status']);
    Route::post('integrations/woocommerce/sync', [\Modules\PriyasaCore\Http\Controllers\Admin\WooCommerceController::class, 'sync'])->middleware('idempotency');
    Route::post('integrations/woocommerce/sync/products', [\Modules\PriyasaCore\Http\Controllers\Admin\WooCommerceController::class, 'products'])->middleware('idempotency');
    Route::post('integrations/woocommerce/sync/orders', [\Modules\PriyasaCore\Http\Controllers\Admin\WooCommerceController::class, 'orders'])->middleware('idempotency');

    Route::get('integrations/meta/status', [\Modules\PriyasaCore\Http\Controllers\Admin\IntegrationController::class, 'metaStatus']);
    Route::get('integrations/meta/connect', [\Modules\PriyasaCore\Http\Controllers\Admin\IntegrationController::class, 'metaConnect']);
    Route::post('integrations/{key}/connect', [\Modules\PriyasaCore\Http\Controllers\Admin\IntegrationController::class, 'connect'])->middleware('idempotency');
    Route::post('integrations/{key}/disconnect', [\Modules\PriyasaCore\Http\Controllers\Admin\IntegrationController::class, 'disconnect'])->middleware('idempotency');

    Route::get('whatsapp/status', [\Modules\PriyasaCore\Http\Controllers\Admin\IntegrationController::class, 'whatsappStatus']);
    Route::get('whatsapp/templates', [\Modules\PriyasaCore\Http\Controllers\Admin\IntegrationController::class, 'whatsappTemplates']);
    Route::post('whatsapp/templates/sync', [\Modules\PriyasaCore\Http\Controllers\Admin\IntegrationController::class, 'whatsappSyncTemplates'])->middleware('idempotency');
    Route::get('whatsapp/conversations', [\Modules\PriyasaCore\Http\Controllers\Admin\WhatsAppController::class, 'conversations']);
    Route::get('whatsapp/conversations/{phone}', [\Modules\PriyasaCore\Http\Controllers\Admin\WhatsAppController::class, 'conversation']);
    Route::post('whatsapp/conversations/{phone}/reply', [\Modules\PriyasaCore\Http\Controllers\Admin\WhatsAppController::class, 'reply'])->middleware('idempotency');

    Route::get('automations', [\Modules\PriyasaCore\Http\Controllers\Admin\AutomationController::class, 'index']);
    Route::put('automations/{automation}', [\Modules\PriyasaCore\Http\Controllers\Admin\AutomationController::class, 'update']);
    Route::post('automations/{automation}/toggle', [\Modules\PriyasaCore\Http\Controllers\Admin\AutomationController::class, 'toggle'])->middleware('idempotency');

    Route::get('catalog/attributes', [AdminAttributeController::class, 'index']);
    Route::post('catalog/attributes', [AdminAttributeController::class, 'store']);
    Route::put('catalog/attributes/{attribute}', [AdminAttributeController::class, 'update']);
    Route::delete('catalog/attributes/{attribute}', [AdminAttributeController::class, 'destroy']);
    Route::post('catalog/attributes/{attribute}/options', [AdminAttributeController::class, 'optionStore']);
    Route::put('catalog/attributes/{attribute}/options/{option}', [AdminAttributeController::class, 'optionUpdate']);
    Route::get('catalog/products', [AdminCatalogController::class, 'index']);
    Route::get('catalog/products/{product}', [AdminCatalogController::class, 'show']);
    Route::post('catalog/products', [AdminCatalogController::class, 'store']);
    Route::put('catalog/products/{product}', [AdminCatalogController::class, 'update']);
    Route::delete('catalog/products/{product}', [AdminCatalogController::class, 'destroy']);
    Route::get('catalog/products/{product}/media', [ProductMediaController::class, 'index']);
    Route::post('catalog/products/{product}/media', [ProductMediaController::class, 'store']);
    Route::put('catalog/products/{product}/media/{media}', [ProductMediaController::class, 'update']);
    Route::delete('catalog/products/{product}/media/{media}', [ProductMediaController::class, 'destroy']);
    Route::get('reviews', [AdminReviewController::class, 'index']);
    Route::post('reviews/{review}/status', [AdminReviewController::class, 'status']);
    Route::get('collections', [AdminCollectionController::class, 'index']);
    Route::get('collections/{collection}', [AdminCollectionController::class, 'show']);
    Route::post('collections', [AdminCollectionController::class, 'store']);
    Route::put('collections/{collection}', [AdminCollectionController::class, 'update']);
    Route::delete('collections/{collection}', [AdminCollectionController::class, 'destroy']);
    Route::get('catalog/categories', [AdminCategoryController::class, 'index']);
    Route::get('catalog/categories/{category}', [AdminCategoryController::class, 'show']);
    Route::post('catalog/categories', [AdminCategoryController::class, 'store']);
    Route::put('catalog/categories/{category}', [AdminCategoryController::class, 'update']);
    Route::delete('catalog/categories/{category}', [AdminCategoryController::class, 'destroy']);
    Route::post('catalog/products/{product}/variants', [AdminCatalogController::class, 'variantStore']);
    Route::put('catalog/products/{product}/variants/{variant}', [AdminCatalogController::class, 'variantUpdate']);
    Route::delete('catalog/products/{product}/variants/{variant}', [AdminCatalogController::class, 'variantDestroy']);
    Route::get('catalog/products/{product}/attributes', [AdminCatalogController::class, 'attributes']);
    Route::put('catalog/products/{product}/attributes', [AdminCatalogController::class, 'syncAttributes']);
    Route::post('catalog/products/{product}/variants/generate', [AdminCatalogController::class, 'generateVariants']);
    Route::put('catalog/products/{product}/variants/{variant}/attribute-options', [AdminCatalogController::class, 'attachVariantOptions']);
    Route::post('catalog/products/bulk-price', [AdminCatalogController::class, 'bulkPrice']);
    Route::get('customers', [AdminCustomerController::class, 'index']);
    Route::get('customers/{customer}', [AdminCustomerController::class, 'show']);
    Route::put('customers/{customer}', [AdminCustomerController::class, 'update']);
    Route::post('customers/{customer}/status', [AdminCustomerController::class, 'status']);
    Route::get('promotions', [AdminPromotionController::class, 'index']);
    Route::get('promotions/campaigns', [AdminPromotionController::class, 'campaigns']);
    Route::post('promotions/campaigns', [AdminPromotionController::class, 'campaignStore']);
    Route::put('promotions/campaigns/{campaign}', [AdminPromotionController::class, 'campaignUpdate']);
    Route::post('promotions/campaigns/{campaign}/toggle', [AdminPromotionController::class, 'campaignToggle']);
    Route::get('promotions/{coupon}', [AdminPromotionController::class, 'show']);
    Route::post('promotions', [AdminPromotionController::class, 'store']);
    Route::put('promotions/{coupon}', [AdminPromotionController::class, 'update']);
    Route::delete('promotions/{coupon}', [AdminPromotionController::class, 'destroy']);
    Route::post('promotions/{coupon}/toggle', [AdminPromotionController::class, 'toggle']);
    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::get('orders/{order}', [AdminOrderController::class, 'show']);
    Route::post('orders/{order}/status', [AdminOrderController::class, 'status']);
    Route::post('orders/{order}/confirm-cod', [AdminOrderController::class, 'confirmCod']);
    Route::post('orders/{order}/refund', [\Modules\PriyasaCore\Http\Controllers\Admin\PaymentController::class, 'refund']);
    Route::get('inventory', [AdminInventoryController::class, 'index']);
    Route::post('inventory/{variant}/adjust', [AdminInventoryController::class, 'adjust']);
    Route::get('inventory/{variant}', [AdminInventoryController::class, 'show']);
    Route::put('inventory/{variant}/stock', [AdminInventoryController::class, 'setStock']);
    Route::get('inventory/{variant}/movements', [AdminInventoryController::class, 'movements']);
    Route::post('inventory/bulk-stock', [AdminInventoryController::class, 'bulkSetStock']);
    Route::get('returns', [AdminReturnController::class, 'index']);
    Route::post('returns/{return}/status', [AdminReturnController::class, 'status']);
    Route::post('orders/{order}/invoice', [AdminInvoiceController::class, 'issue']);
    Route::get('orders/{order}/invoice', [AdminInvoiceController::class, 'show']);
    Route::get('settings', [SettingsController::class, 'index']);
    Route::put('settings', [SettingsController::class, 'update']);
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('shipping/shipments', [ShippingController::class, 'index']);
    Route::post('shipping/orders/{order}/shipments', [ShippingController::class, 'create']);
    Route::post('shipping/shipments/{shipment}/awb', [ShippingController::class, 'awb']);
    Route::post('shipping/shipments/{shipment}/track', [ShippingController::class, 'track']);
    Route::post('shipping/shipments/{shipment}/cancel', [ShippingController::class, 'cancel']);
    Route::get('analytics', [AnalyticsController::class, 'index']);
    Route::get('admins', [AdminUserController::class, 'index']);
    Route::get('roles', [AdminUserController::class, 'roles']);
    Route::post('admins', [AdminUserController::class, 'store']);
    Route::put('admins/{admin}', [AdminUserController::class, 'update']);
    Route::post('admins/{admin}/status', [AdminUserController::class, 'status']);
    Route::get('merchandising/products', [MerchandisingController::class, 'products']);
    Route::patch('merchandising/products/{product}', [MerchandisingController::class, 'update']);
    Route::post('merchandising/products/reorder', [MerchandisingController::class, 'reorder']);
    Route::post('merchandising/collections/{collection}/reorder', [MerchandisingController::class, 'collectionReorder']);
    Route::get('cms', [AdminCmsController::class, 'index']);
    Route::post('cms', [AdminCmsController::class, 'store']);
    Route::get('cms/{section}', [AdminCmsController::class, 'show']);
    Route::put('cms/{section}', [AdminCmsController::class, 'update']);
    Route::delete('cms/{section}', [AdminCmsController::class, 'destroy']);
    Route::post('cms/reorder', [AdminCmsController::class, 'reorder']);
    Route::get('cms/preview', [AdminCmsController::class, 'preview']);
    Route::post('cms/drafts', [AdminCmsController::class, 'saveDraft']);
    Route::get('cms/versions', [AdminCmsController::class, 'versions']);
    Route::post('cms/versions/{version}/publish', [AdminCmsController::class, 'publish']);
});

});

// P9 Notification & Automation routes
require __DIR__ . '/p9.php';

// P10 Production operations / observability endpoints.
require __DIR__ . '/p10.php';

require __DIR__.'/p11.php';
