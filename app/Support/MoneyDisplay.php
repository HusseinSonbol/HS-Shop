<?php

namespace App\Support;

final class MoneyDisplay
{
    public static function decimal(mixed $value): string
    {
        $amount = trim((string) ($value ?? '0'));
        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '+-');
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?? $whole;

        return ($negative ? '-' : '').$whole.'.'.str_pad(substr($fraction, 0, 4), 4, '0');
    }
}
