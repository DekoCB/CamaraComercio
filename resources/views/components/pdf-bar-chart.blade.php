@props([
    'title' => null,
    'categories' => [],
    'values' => [],
    'colors' => ['#2f6690', '#8a3b38', '#3d7a4f', '#c98a2c', '#5b5f97', '#57606b'],
    'prefix' => 'S/ ',
])
@php
    $w = 520;
    $h = 240;
    $marginLeft = 46;
    $marginRight = 12;
    $marginTop = $title ? 30 : 14;
    $marginBottom = 34;
    $plotW = $w - $marginLeft - $marginRight;
    $plotH = $h - $marginTop - $marginBottom;
    $n = count($values);
    $rawMax = $n > 0 ? max(0, max($values)) : 0;

    // A "nice" axis top (next 1/2/5/10 × 10^n above the data) so gridlines
    // land on round numbers instead of the tallest bar's exact value.
    $niceStep = function (float $max, int $targetTicks = 4): float {
        if ($max <= 0) {
            return 1;
        }
        $rough = $max / $targetTicks;
        $magnitude = 10 ** floor(log10($rough));
        $residual = $rough / $magnitude;
        $niceResidual = $residual <= 1 ? 1 : ($residual <= 2 ? 2 : ($residual <= 5 ? 5 : 10));

        return $niceResidual * $magnitude;
    };
    $step = $niceStep($rawMax);
    $ticks = max(1, (int) ceil(($rawMax ?: $step) / $step));
    $axisTop = $step * $ticks;

    $barSlot = $n > 0 ? $plotW / $n : $plotW;
    $barW = min($barSlot * 0.55, 64);
    $valueY = fn (float $v) => $marginTop + $plotH - ($axisTop > 0 ? ($v / $axisTop) * $plotH : 0);
@endphp
@php
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'" height="'.$h.'">';
    $svg .= '<rect x="'.$marginLeft.'" y="'.$marginTop.'" width="'.$plotW.'" height="'.$plotH.'" fill="#fafbfc" />';
    if ($title) {
        $svg .= '<text x="'.($w / 2).'" y="16" text-anchor="middle" font-size="12.5" font-weight="bold" fill="#22303f">'.e($title).'</text>';
    }
    for ($t = 0; $t <= $ticks; $t++) {
        $gridValue = $t * $step;
        $y = $valueY($gridValue);
        $svg .= '<line x1="'.$marginLeft.'" y1="'.$y.'" x2="'.($w - $marginRight).'" y2="'.$y.'" stroke="#e7ebef" stroke-width="1" />';
        $svg .= '<text x="'.($marginLeft - 6).'" y="'.($y + 3).'" text-anchor="end" font-size="8" fill="#8a94a1">'.e(number_format($gridValue, 0)).'</text>';
    }
    $svg .= '<line x1="'.$marginLeft.'" y1="'.($marginTop + $plotH).'" x2="'.($w - $marginRight).'" y2="'.($marginTop + $plotH).'" stroke="#c3cbd3" stroke-width="1" />';

    foreach ($values as $i => $v) {
        $barH = $marginTop + $plotH - $valueY((float) $v);
        $x = $marginLeft + $i * $barSlot + ($barSlot - $barW) / 2;
        $y = $valueY((float) $v);
        $color = $colors[$i % count($colors)];
        $svg .= '<rect x="'.$x.'" y="'.$y.'" width="'.$barW.'" height="'.max(0, $barH).'" rx="2" fill="'.$color.'" />';
        $svg .= '<text x="'.($x + $barW / 2).'" y="'.($y - 5).'" text-anchor="middle" font-size="9.5" font-weight="bold" fill="#22303f">'.e($prefix.number_format((float) $v, 0)).'</text>';
        $svg .= '<text x="'.($x + $barW / 2).'" y="'.($marginTop + $plotH + 15).'" text-anchor="middle" font-size="9" fill="#5b6570">'.e(\Illuminate\Support\Str::limit($categories[$i] ?? '', 14)).'</text>';
    }
    $svg .= '</svg>';
@endphp
<div style="text-align: center;">
    <img src="data:image/svg+xml;base64,{{ base64_encode($svg) }}" width="{{ $w }}" height="{{ $h }}" alt="{{ $title }}">
</div>
