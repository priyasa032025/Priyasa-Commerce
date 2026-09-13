<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\Inventory;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\Payment;
use Modules\PriyasaCore\Models\Product;
use Modules\PriyasaCore\Models\ProductVariant;
use Modules\PriyasaCore\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('completes checkout, signed payment webhook and paid-order cancellation without double stock movement', function () {
    config(['priyasacore.razorpay.webhook_secret' => 'webhook-test-secret']);

    $customer = Customer::create(['phone'=>'+919999999999','first_name'=>'E2E','last_name'=>'Customer','phone_verified_at'=>now()]);
    $address = $customer->addresses()->create(['recipient_name'=>'E2E Customer','phone'=>'+919999999999','line1'=>'1 Test Road','city'=>'Noida','state'=>'UP','postal_code'=>'201301','country'=>'IN','is_default'=>true]);
    $product = Product::create(['name'=>'E2E Kurta','slug'=>'e2e-kurta','price'=>1499,'mrp'=>1999,'status'=>'published']);
    $variant = ProductVariant::create(['product_id'=>$product->id,'sku'=>'E2E-KURTA-M-BLK','size'=>'M','color'=>'Black','price'=>1499,'mrp'=>1999,'is_active'=>true]);
    Inventory::create(['variant_id'=>$variant->id,'quantity'=>5,'reserved_quantity'=>0]);

    $this->actingAs($customer,'sanctum');
    $this->postJson('/api/v1/storefront/cart/items',['variant_id'=>$variant->id,'quantity'=>2])->assertOk();
    $this->postJson('/api/v1/storefront/checkout/validate')->assertOk()->assertJsonPath('data.grand_total',2998);
    $create=$this->postJson('/api/v1/storefront/checkout/create-order',['shipping_address_id'=>$address->id,'payment_method'=>'razorpay'])->assertCreated();
    $order=Order::findOrFail($create->json('data.id'));
    expect($order->status)->toBe('pending_payment');
    expect((int)Inventory::where('variant_id',$variant->id)->value('reserved_quantity'))->toBe(2);

    Payment::create(['order_id'=>$order->id,'provider'=>'razorpay','provider_order_id'=>'order_e2e_1','status'=>'created','amount'=>2998,'currency'=>'INR','payload'=>[]]);
    $payload=['event'=>'payment.captured','payload'=>['payment'=>['entity'=>['id'=>'pay_e2e_1','order_id'=>'order_e2e_1','amount'=>299800,'currency'=>'INR','status'=>'captured']]]];
    $raw=json_encode($payload,JSON_THROW_ON_ERROR);
    $signature=hash_hmac('sha256',$raw,'webhook-test-secret');
    $this->withHeader('X-Razorpay-Signature',$signature)->withHeader('X-Razorpay-Event-Id','evt_e2e_1')->postJson('/api/v1/webhooks/razorpay', $payload)->assertStatus(202);

    $order->refresh();
    $inventory=Inventory::where('variant_id',$variant->id)->firstOrFail();
    expect($order->status)->toBe('confirmed')->and($order->payment_status)->toBe('paid');
    expect((int)$inventory->quantity)->toBe(3)->and((int)$inventory->reserved_quantity)->toBe(0);

    $this->postJson('/api/v1/storefront/orders/'.$order->id.'/cancel')->assertOk();
    $order->refresh(); $inventory->refresh();
    expect($order->status)->toBe('cancelled')->and($order->payment_status)->toBe('refund_pending');
    expect((int)$inventory->quantity)->toBe(5)->and((int)$inventory->reserved_quantity)->toBe(0);
});

it('rejects an invalid Razorpay webhook signature', function () {
    config(['priyasacore.razorpay.webhook_secret' => 'webhook-test-secret']);
    $this->withHeader('X-Razorpay-Signature','invalid')->postJson('/api/v1/webhooks/razorpay',['event'=>'payment.captured'])->assertUnauthorized();
});
