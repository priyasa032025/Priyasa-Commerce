<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
 'module.json','Config/config.php','Routes/api.php',
 'Services/InventoryService.php','Services/PricingService.php','Services/OrderService.php','Services/CheckoutService.php','Services/PaymentService.php',
 'Contracts/PaymentGateway.php','Contracts/ShippingProvider.php','Contracts/CommerceConnector.php',
 'Database/Migrations/2026_09_10_000001_create_priyasa_core_catalog.php',
 'Database/Migrations/2026_09_10_000002_create_priyasa_core_customers.php',
 'Database/Migrations/2026_09_10_000003_create_priyasa_core_cart_checkout.php',
 'Database/Migrations/2026_09_10_000004_create_priyasa_core_payments_promotions.php',
 'Database/Migrations/2026_09_10_000005_create_priyasa_core_returns_audit.php',
 'Database/Migrations/2026_09_10_000006_create_priyasa_core_growth_and_operations.php',
 'Database/Migrations/2026_09_10_000007_create_priyasa_core_rbac.php',
 'Database/Migrations/2026_09_13_000001_p0_identity_merge.php',
 'Services/CustomerResolver.php','Services/OTPService.php','Services/DeviceTokenLifecycleService.php',
 'Http/Controllers/Api/V1/Auth/OTPController.php','Http/Controllers/Api/V1/DeviceToken/DeviceController.php',
];
$missing=[]; foreach($required as $f) if(!is_file($root.'/'.$f)) $missing[]=$f;
if($missing){fwrite(STDERR,"Missing files:\n".implode("\n",$missing)."\n");exit(1);}
$route=file_get_contents($root.'/Routes/api.php');
foreach(['auth/send-otp','auth/verify-otp','device/register','device/update','products','cart','checkout/create-order','orders/{order}/payment','wishlist','reviews','returns','webhooks/{provider}','health'] as $needle){if(strpos($route,$needle)===false){fwrite(STDERR,"Missing route contract: $needle\n");exit(1);}}
$trans=file_get_contents($root.'/Services/OrderService.php');
foreach(['pending_payment','confirmed','processing','packed','shipped','in_transit','out_for_delivery','delivered','return_requested','returned','refunded'] as $state){if(strpos($trans,"'$state'")===false){fwrite(STDERR,"Missing order state: $state\n");exit(1);}}
echo "PRIYASA CORE CONTRACT TEST: PASS\n";
