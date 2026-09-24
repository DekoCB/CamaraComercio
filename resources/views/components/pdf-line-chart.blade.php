@props([
    'title' => null,
    'categories' => [],
    'series' => [],
    'colors' => ['#2f6690', '#8a3b38', '#3d7a4f', '#c98a2c'],
    'prefix' => 'S/ ',
])
@php
    $w = 520;
    $h = 260;
    $marginLeft = 46;
    $marginRight = 12;
    $marginTop = $title ? 30 : 14;
    $marginBottom = 32;
    $marginLegend = 20;
    $plotW = $w - $marginLeft - $marginRight;
    $plotH = $h - $marginTop - $marginBottom - $marginLegend;
    $n = count($categories);
    $allValues = array_merge(...array_values($series ?: [[]]));
    $rawMax = $allValues !== [] ? max(0, max($allValues)) : 0;

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

    $stepX = $n > 1 ? $plotW / ($n - 1) : 0;
    $pointX = fn (int $i) => $marginLeft + ($n > 1 ? $i * $stepX : $plotW / 2);
    $pointY = fn (float $v) => $marginTop + $plotH - ($axisTop > 0 ? ($v / $axisTop) * $plotH : 0);
@endphp
@php
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'" height="'.$h.'">';
    $svg .= '<rect x="'.$marginLeft.'" y="'.$marginTop.'" width="'.$plotW.'" height="'.$plotH.'" fill="#fafbfc" />';
    if ($title) {
        $svg .= '<text x="'.($w / 2).'" y="16" text-anchor="middle" font-size="12.5" font-weight="bold" fill="#22303f">'.e($title).'</text>';
    }
    for ($t = 0; $t <= $ticks; $t++) {
        $gridValue = $t * $step;
        $y = $pointY($gridValue);
        $svg .= '<line x1="'.$marginLeft.'" y1="'.$y.'" x2="'.($w - $marginRight).'" y2="'.$y.'" stroke="#e7ebef" stroke-width="1" />';
        $svg .= '<text x="'.($marginLeft - 6).'" y="'.($y + 3).'" text-anchor="end" font-size="8" fill="#8a94a1">'.e(number_format($gridValue, 0)).'</text>';
    }
    $svg .= '<line x1="'.$marginLeft.'" y1="'.($marginTop + $plotH).'" x2="'.($w - $marginRight).'" y2="'.($marginTop + $plotH).'" stroke="#c3cbd3" stroke-width="1" />';

    $seriesIndex = 0;
    foreach ($series as $label => $values) {
        $values = array_values($values);
        $color = $colors[$seriesIndex % count($colors)];
        $points = [];
        foreach ($values as $i => $v) {
            $points[] = $pointX($i).','.$pointY((float) $v);
        }
        $svg .= '<polyline points="'.implode(' ', $points).'" fill="none" stroke="'.$color.'" stroke-width="2.25" stroke-linejoin="round" stroke-linecap="round" />';
        foreach ($values as $i => $v) {
            $svg .= '<circle cx="'.$pointX($i).'" cy="'.$pointY((float) $v).'" r="3" fill="#ffffff" stroke="'.$color.'" stroke-width="2" />';
        }
        $seriesIndex++;
    }

    foreach ($categories as $i => $category) {
        $svg .= '<text x="'.$pointX($i).'" y="'.($marginTop + $plotH + 15).'" text-anchor="middle" font-size="8.5" fill="#5b6570">'.e($category).'</text>';
    }

    $legendWidths = [];
    foreach ($series as $label => $values) {
        $legendWidths[] = 18 + strlen((string) $label) * 5.5;
    }
    $legendTotalWidth = array_sum($legendWidths);
    $legendX = $marginLeft + max(0, ($plotW - $legendTotalWidth) / 2);
    $seriesIndex = 0;
    foreach ($series as $label => $values) {
        $color = $colors[$seriesIndex % count($colors)];
        $svg .= '<rect x="'.$legendX.'" y="'.($h - $marginLegend + 5).'" width="9" height="9" rx="2" fill="'.$color.'" />';
        $svg .= '<text x="'.($legendX + 13).'" y="'.($h - $marginLegend + 13).'" font-size="9" fill="#22303f">'.e($label).'</text>';
        $legendX += $legendWidths[$seriesIndex];
        $seriesIndex++;
    }

    $svg .= '</svg>';
@endphp
<div style="text-align: center;">
    <img src="data:image/svg+xml;base64,{{ base64_encode($svg) }}" width="{{ $w }}" height="{{ $h }}" alt="{{ $title }}">
</div>
