@props([
    'title' => null,
    'labels' => [],
    'values' => [],
    'colors' => ['#2f6690', '#c98a2c', '#8a3b38', '#3d7a4f', '#5b5f97', '#57606b'],
    'prefix' => 'S/ ',
])
@php
    $w = 340;
    $h = 230;
    $cx = 108;
    $cy = 122;
    $r = 76;
    $holeR = $r * 0.58;
    $total = array_sum($values);
    $cursor = -90.0;
    $slices = [];
    foreach ($values as $i => $v) {
        $sweep = $total > 0 ? ((float) $v / $total) * 360 : 0;
        $slices[] = ['start' => $cursor, 'end' => $cursor + $sweep, 'color' => $colors[$i % count($colors)]];
        $cursor += $sweep;
    }
    $toXY = fn (float $angleDeg) => [$cx + $r * cos(deg2rad($angleDeg)), $cy + $r * sin(deg2rad($angleDeg))];
    $nonZeroCount = count(array_filter($values, fn ($v) => (float) $v > 0));
@endphp
@php
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'" height="'.$h.'">';
    if ($title) {
        $svg .= '<text x="'.($w / 2).'" y="16" text-anchor="middle" font-size="12.5" font-weight="bold" fill="#22303f">'.e($title).'</text>';
    }
    foreach ($slices as $i => $slice) {
        if ($total <= 0 || (float) ($values[$i] ?? 0) <= 0) {
            continue;
        }
        // A single 100% slice sweeps a full 360°, where the SVG arc's
        // start and end points coincide and the path collapses to
        // nothing — draw a plain circle instead in that one case.
        if ($nonZeroCount === 1) {
            $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="'.$slice['color'].'" stroke="#ffffff" stroke-width="1.5" />';

            continue;
        }
        [$x1, $y1] = $toXY($slice['start']);
        [$x2, $y2] = $toXY($slice['end']);
        $large = ($slice['end'] - $slice['start']) > 180 ? 1 : 0;
        $svg .= '<path d="M '.$cx.','.$cy.' L '.$x1.','.$y1.' A '.$r.','.$r.' 0 '.$large.' 1 '.$x2.','.$y2.' Z" fill="'.$slice['color'].'" stroke="#ffffff" stroke-width="1.5" />';
    }
    if ($total > 0) {
        // Donut hole, painted over the slice centers — masks the pie into
        // a ring so the total can sit legibly in the middle.
        $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$holeR.'" fill="#ffffff" />';
        $svg .= '<text x="'.$cx.'" y="'.($cy - 3).'" text-anchor="middle" font-size="8.5" fill="#8a94a1">Total</text>';
        $svg .= '<text x="'.$cx.'" y="'.($cy + 13).'" text-anchor="middle" font-size="12" font-weight="bold" fill="#22303f">'.e($prefix.number_format($total, 0)).'</text>';
    }
    $legendY = 44;
    foreach ($labels as $i => $label) {
        $color = $colors[$i % count($colors)];
        $pct = $total > 0 ? round(((float) ($values[$i] ?? 0) / $total) * 100) : 0;
        $svg .= '<rect x="220" y="'.$legendY.'" width="10" height="10" rx="2" fill="'.$color.'" />';
        $svg .= '<text x="235" y="'.($legendY + 9).'" font-size="9.5" fill="#22303f">'.e(\Illuminate\Support\Str::limit($label, 15)).'</text>';
        $svg .= '<text x="235" y="'.($legendY + 21).'" font-size="8.5" fill="#8a94a1">'.e($prefix.number_format((float) ($values[$i] ?? 0), 0).' · '.$pct.'%').'</text>';
        $legendY += 30;
    }
    $svg .= '</svg>';
@endphp
<div style="text-align: center;">
    <img src="data:image/svg+xml;base64,{{ base64_encode($svg) }}" width="{{ $w }}" height="{{ $h }}" alt="{{ $title }}">
</div>
