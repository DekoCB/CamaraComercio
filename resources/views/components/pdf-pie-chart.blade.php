@props([
    'title' => null,
    'labels' => [],
    'values' => [],
    'colors' => ['#2f6690', '#c98a2c', '#8a3b38', '#3d7a4f', '#5b5f97', '#57606b'],
    'prefix' => 'S/ ',
])
@php
    $w = 320;
    $h = 220;
    $cx = 95;
    $cy = 112;
    $r = 78;
    $total = array_sum($values);
    $cursor = -90.0;
    $slices = [];
    foreach ($values as $i => $v) {
        $sweep = $total > 0 ? ((float) $v / $total) * 360 : 0;
        $slices[] = ['start' => $cursor, 'end' => $cursor + $sweep, 'color' => $colors[$i % count($colors)]];
        $cursor += $sweep;
    }
    $toXY = fn (float $angleDeg) => [$cx + $r * cos(deg2rad($angleDeg)), $cy + $r * sin(deg2rad($angleDeg))];

    // Same reasoning as pdf-bar-chart: Dompdf only understands SVG through
    // its image renderer, so this is built as a string and embedded as a
    // data URI rather than written as inline <svg> markup.
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'" height="'.$h.'">';
    if ($title) {
        $svg .= '<text x="'.($w / 2).'" y="14" text-anchor="middle" font-size="12" font-weight="bold" fill="#22303f">'.e($title).'</text>';
    }
    $nonZeroCount = count(array_filter($values, fn ($v) => (float) $v > 0));
    foreach ($slices as $i => $slice) {
        if ($total <= 0 || (float) ($values[$i] ?? 0) <= 0) {
            continue;
        }
        // A single 100% slice sweeps a full 360°, where the SVG arc's
        // start and end points coincide and the path collapses to
        // nothing — draw a plain circle instead in that one case.
        if ($nonZeroCount === 1) {
            $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="'.$slice['color'].'" stroke="#ffffff" stroke-width="1" />';

            continue;
        }
        [$x1, $y1] = $toXY($slice['start']);
        [$x2, $y2] = $toXY($slice['end']);
        $large = ($slice['end'] - $slice['start']) > 180 ? 1 : 0;
        $svg .= '<path d="M '.$cx.','.$cy.' L '.$x1.','.$y1.' A '.$r.','.$r.' 0 '.$large.' 1 '.$x2.','.$y2.' Z" fill="'.$slice['color'].'" stroke="#ffffff" stroke-width="1" />';
    }
    foreach ($labels as $i => $label) {
        $legendY = 34 + $i * 16;
        $color = $colors[$i % count($colors)];
        $svg .= '<rect x="198" y="'.$legendY.'" width="10" height="10" fill="'.$color.'" />';
        $svg .= '<text x="213" y="'.($legendY + 9).'" font-size="9" fill="#22303f">'.e(\Illuminate\Support\Str::limit($label, 16).': '.$prefix.number_format((float) ($values[$i] ?? 0), 0)).'</text>';
    }
    $svg .= '</svg>';
@endphp
<img src="data:image/svg+xml;base64,{{ base64_encode($svg) }}" width="{{ $w }}" height="{{ $h }}" alt="{{ $title }}">
