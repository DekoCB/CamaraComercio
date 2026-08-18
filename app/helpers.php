<?php

declare(strict_types=1);
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

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

if (! function_exists('icon')) {
    /**
     * Inlines a Lucide icon SVG (vendored under public/assets/icons/, no
     * CDN/icon-font). Returns HtmlString so `{{ icon(...) }}` in Blade
     * doesn't get double-escaped. stroke="currentColor" in the source
     * SVGs means the icon always follows the surrounding text color —
     * no per-icon color overrides needed.
     */
    function icon(string $name, string $class = 'icon', int $size = 20): HtmlString
    {
        static $cache = [];

        if (! array_key_exists($name, $cache)) {
            $path = public_path("assets/icons/{$name}.svg");
            $cache[$name] = is_file($path) ? (string) file_get_contents($path) : '';
        }

        $svg = $cache[$name];
        if ($svg === '') {
            return new HtmlString('<!-- missing icon: '.e($name).' -->');
        }

        $svg = preg_replace('/width="24" height="24"/', 'width="'.$size.'" height="'.$size.'"', $svg, 1);
        $svg = preg_replace('/class="lucide[^"]*"/', 'class="'.e($class).'" aria-hidden="true" focusable="false"', $svg, 1);

        return new HtmlString($svg);
    }
}
