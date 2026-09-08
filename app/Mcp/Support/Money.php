<?php

namespace App\Mcp\Support;

final class Money
{
    public const CURRENCY = 'CAD';

    public static function display(int $cents): string
    {
        return sprintf('$%s %s', number_format($cents / 100, 2), self::CURRENCY);
    }
}
