<?php

namespace Amplify\System\Utility\Helpers;

use InvalidArgumentException;

/**
 * Class CurrencyHelper
 */
class CurrencyHelper
{
    /**
     * Return config array of a specific code
     */
    public static function config(?string $code = null): object
    {
        $code = ($code == null) ? config('amplify.basic.global_currency') : $code;

        $currency = config("amplify.constant.currency.{$code}");

        if ($currency == null) {
            throw new InvalidArgumentException("The currency code ({$code}) is invalid or not exists is list.");
        }

        return (object) $currency;

    }

    /**
     * Return a numeric value to currency formatted value
     */
    public static function format($value = null, ?string $code = null, bool $withSymbol = false): string
    {
        if ($value != null) {
            $currency = currency($code);

            if (! is_numeric($value)) {
                throw new InvalidArgumentException("The given value ({$value}) is invalid.");
            }

            $money = number_format($value, $currency->precision, $currency->decimal_mark, $currency->thousands_separator);

            if ($withSymbol) {
                if ($currency->symbol_first) {
                    $money = "{$currency->symbol}{$money}";
                } else {
                    $money = "{$money}{$currency->symbol}";
                }
            }

            return $money;
        }

        return '-';
    }
}
