<?php

declare(strict_types=1);
use Illuminate\Support\Carbon;

if (! function_exists('format_money')) {
    function format_money(float|string $amount): string
    {
        return 'S/ '.number_format((float) $amount, 2);
    }
}

if (! function_exists('format_date')) {
    function format_date(mixed $date): string
    {
        if (! $date) {
            return '-';
        }

        return Carbon::parse($date)->format('d/m/Y');
    }
}
