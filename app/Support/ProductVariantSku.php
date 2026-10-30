<?php

namespace App\Support;

use Illuminate\Support\Str;

class ProductVariantSku
{
    /** @param array<string, mixed> $variant */
    public static function base(string $productSku, string $productType, array $variant, int $position): string
    {
        $attributes = [];
        foreach (['size_en', 'color_en'] as $field) {
            if (filled($variant[$field] ?? null)) {
                $attributes[] = Str::slug((string) $variant[$field]);
            }
        }

        $identity = $productType === 'simple' ? str_pad((string) $position, 2, '0', STR_PAD_LEFT) : implode('-', $attributes);
        $identity = Str::limit($identity ?: str_pad((string) $position, 2, '0', STR_PAD_LEFT), 50, '');
        $familyLength = max(1, 80 - strlen($identity) - 1);
        $family = rtrim(substr(Str::upper($productSku), 0, $familyLength), '-');

        return Str::upper($family.'-'.$identity);
    }
}
