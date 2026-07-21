<?php

namespace App\Support;

class SwapilesPricing
{
    public static function protectionFee(float|int $price): float
    {
        if ($price <= 0) {
            return 0;
        }

        return max(1, round($price * 0.10, 2));
    }

    public static function protectedTotal(float|int $price): float
    {
        return round($price + self::protectionFee($price), 2);
    }
}
