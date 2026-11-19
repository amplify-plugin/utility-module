<?php

namespace Amplify\System\Utility\Listeners;

use Amplify\System\Backend\Models\Product;
use WeakMap;

/** Preserve flags only on product instances handled by the display-field import. */
class ImportedProductFlags
{
    private static ?WeakMap $products = null;

    public static function track(Product $product): void
    {
        self::$products ??= new WeakMap;
        self::$products[$product] = $product->flags;
    }

    public static function forget(Product $product): void
    {
        if (self::$products !== null) {
            unset(self::$products[$product]);
        }
    }

    public function saving(Product $product): void
    {
        if (isset(self::$products[$product])) {
            // Capture any subsequent data transformations before model callbacks run.
            self::$products[$product] = $product->flags;
        }
    }

    public function creating(Product $product): void
    {
        if (isset(self::$products[$product])) {
            $product->flags = self::$products[$product];
        }
    }

    public function updating(Product $product): void
    {
        $this->creating($product);
    }
}
