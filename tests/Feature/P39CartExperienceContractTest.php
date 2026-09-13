<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Tests\Feature;

final class P39CartExperienceContractTest
{
    public static function expected(): array
    {
        return [
            'guest-cart-token' => 'X-Cart-Token',
            'cart-response-type' => 'cart.experience',
            'update-response-type' => 'cart.updated',
            'checkout-authority' => 'server-recalculates',
        ];
    }
}
