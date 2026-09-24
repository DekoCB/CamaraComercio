@props([
    'title' => null,
    'categories' => [],
    'values' => [],
    'colors' => ['#2f6690', '#8a3b38', '#3d7a4f', '#c98a2c', '#5b5f97', '#57606b'],
    'prefix' => 'S/ ',
])
@php
    $w = 500;
    $h = 220;
    $marginLeft = 8;
    $marginRight = 8;
    $marginTop = $title ? 26 : 10;
    $marginBottom = 32;
    $plotW = $w - $marginLeft - $marginRight;
    $plotH = $h - $marginTop - $marginBottom;
    $n = count($values);
    $max = $n > 0 ? max(1, max($values)) : 1;
    $barSlot = $n > 0 ? $plotW / $n : $plotW;
    $barW = $barSlot * 0.55;

    /**
     * Dompdf does not lay out inline <svg> markup as part of the HTML box
     * tree (there is no SVG frame reflower for it) — only its image
     * renderer understands SVG, via <img src="..."> pointing at an SVG
     * file or data URI (Adapter\CPDF::image() -> addSvgFromFile()). So
     * the chart is built as a standalone SVG string here and embedded as
     * a base64 data URI image, not written inline.
     */
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'" height="'.$h.'">';
    if ($title) {
        $svg .= '<text x="'.($w / 2).'" y="14" text-anchor="middle" font-size="12" font-weight="bold" fill="#22303f">'.e($title).'</text>';
    }
    $svg .= '<line x1="'.$marginLeft.'" y1="'.($marginTop + $plotH).'" x2="'.($w - $marginRight).'" y2="'.($marginTop + $plotH).'" stroke="#dee2e6" stroke-width="1" />';
    foreach ($values as $i => $v) {
        $barH = $max > 0 ? ((float) $v / $max) * $plotH : 0;
        $x = $marginLeft + $i * $barSlot + ($barSlot - $barW) / 2;
        $y = $marginTop + $plotH - $barH;
        $color = $colors[$i % count($colors)];
        $svg .= '<rect x="'.$x.'" y="'.$y.'" width="'.$barW.'" height="'.$barH.'" fill="'.$color.'" />';
        $svg .= '<text x="'.($x + $barW / 2).'" y="'.($y - 4).'" text-anchor="middle" font-size="9" fill="#22303f">'.e($prefix.number_format((float) $v, 0)).'</text>';
        $svg .= '<text x="'.($x + $barW / 2).'" y="'.($marginTop + $plotH + 14).'" text-anchor="middle" font-size="9" fill="#6c7a89">'.e(\Illuminate\Support\Str::limit($categories[$i] ?? '', 14)).'</text>';
    }
    $svg .= '</svg>';
@endphp
<img src="data:image/svg+xml;base64,{{ base64_encode($svg) }}" width="{{ $w }}" height="{{ $h }}" alt="{{ $title }}">
