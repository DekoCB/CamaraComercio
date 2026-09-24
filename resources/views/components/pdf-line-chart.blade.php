@props([
    'title' => null,
    'categories' => [],
    'series' => [],
    'colors' => ['#2f6690', '#8a3b38', '#3d7a4f', '#c98a2c'],
    'prefix' => 'S/ ',
])
@php
    $w = 500;
    $h = 240;
    $marginLeft = 8;
    $marginRight = 8;
    $marginTop = $title ? 26 : 10;
    $marginBottom = 32;
    $marginLegend = 16;
    $plotW = $w - $marginLeft - $marginRight;
    $plotH = $h - $marginTop - $marginBottom - $marginLegend;
    $n = count($categories);
    $allValues = array_merge(...array_values($series ?: [[]]));
    $max = $allValues !== [] ? max(1, max($allValues)) : 1;
    $stepX = $n > 1 ? $plotW / ($n - 1) : 0;
    $pointX = fn (int $i) => $marginLeft + ($n > 1 ? $i * $stepX : $plotW / 2);
    $pointY = fn (float $v) => $marginTop + $plotH - ($max > 0 ? ($v / $max) * $plotH : 0);

    // Same reasoning as the other pdf-*-chart components: Dompdf only
    // understands SVG through its image renderer (Adapter\CPDF::image()
    // -> addSvgFromFile()), not inline <svg> markup in the HTML flow, so
    // this is built as a string and embedded as a base64 data URI.
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'" height="'.$h.'">';
    if ($title) {
        $svg .= '<text x="'.($w / 2).'" y="14" text-anchor="middle" font-size="12" font-weight="bold" fill="#22303f">'.e($title).'</text>';
    }
    $svg .= '<line x1="'.$marginLeft.'" y1="'.($marginTop + $plotH).'" x2="'.($w - $marginRight).'" y2="'.($marginTop + $plotH).'" stroke="#dee2e6" stroke-width="1" />';

    $seriesIndex = 0;
    foreach ($series as $label => $values) {
        $values = array_values($values);
        $color = $colors[$seriesIndex % count($colors)];
        $points = [];
        foreach ($values as $i => $v) {
            $points[] = $pointX($i).','.$pointY((float) $v);
        }
        $svg .= '<polyline points="'.implode(' ', $points).'" fill="none" stroke="'.$color.'" stroke-width="2" />';
        foreach ($values as $i => $v) {
            $svg .= '<circle cx="'.$pointX($i).'" cy="'.$pointY((float) $v).'" r="2.5" fill="'.$color.'" />';
        }
        $seriesIndex++;
    }

    foreach ($categories as $i => $category) {
        $svg .= '<text x="'.$pointX($i).'" y="'.($marginTop + $plotH + 14).'" text-anchor="middle" font-size="8" fill="#6c7a89">'.e($category).'</text>';
    }

    $legendX = $marginLeft;
    $seriesIndex = 0;
    foreach ($series as $label => $values) {
        $color = $colors[$seriesIndex % count($colors)];
        $svg .= '<rect x="'.$legendX.'" y="'.($h - $marginLegend + 4).'" width="9" height="9" fill="'.$color.'" />';
        $svg .= '<text x="'.($legendX + 13).'" y="'.($h - $marginLegend + 12).'" font-size="9" fill="#22303f">'.e($label).'</text>';
        $legendX += 16 + strlen((string) $label) * 5.5;
        $seriesIndex++;
    }

    $svg .= '</svg>';
@endphp
<img src="data:image/svg+xml;base64,{{ base64_encode($svg) }}" width="{{ $w }}" height="{{ $h }}" alt="{{ $title }}">
