<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1a1a1a; }
        .letterhead { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .letterhead td { border: none; vertical-align: middle; }
        .letterhead .logo-cell { width: 54px; }
        .letterhead .logo-cell img { width: 48px; height: 48px; }
        .letterhead .brand { font-style: italic; font-size: 10px; line-height: 1.3; color: #1a1a1a; }
        .letterhead .brand strong { font-style: normal; }
        .letterhead .contact { text-align: right; font-size: 9px; color: #333; }
        .rule { border-top: 1.5px solid #0F2747; margin: 4px 0 10px; }
        h1.inst { font-size: 13px; text-align: center; margin: 0 0 14px; letter-spacing: .02em; }
        .date-line { text-align: right; margin-bottom: 10px; }
        .salutation { font-weight: bold; margin-bottom: 8px; }
        p.lede { text-align: justify; line-height: 1.5; margin-bottom: 12px; }

        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.meta th, table.meta td { border: 1px solid #999; padding: 4px 8px; font-size: 10px; }
        table.meta th { background: #f0f0f0; text-align: left; width: 28%; }

        h2.section { font-size: 11px; text-transform: uppercase; margin: 0 0 6px; color: #0F2747; }

        table.items { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.items th { background: #0F2747; color: #fff; padding: 4px 6px; font-size: 9px; text-transform: uppercase; text-align: left; }
        table.items th.is-numeric, table.items td.is-numeric { text-align: right; }
        table.items td { border: 1px solid #ccd3dc; padding: 4px 6px; font-size: 9.5px; }
        table.items tr:nth-child(even) td { background: #F0F4F8; }
        table.items tr.total td { background: #0F2747; color: #fff; font-weight: bold; }

        .legal-text { font-size: 8.5px; line-height: 1.5; text-align: justify; margin: 10px 0; }
        .legal-text li { margin-bottom: 4px; }

        .grand-total { background: #FFF3B0; padding: 8px 10px; font-weight: bold; margin: 10px 0; }

        .payment { margin: 10px 0; }
        .payment h3 { font-size: 10.5px; margin: 0 0 4px; }
        .payment pre { font-family: DejaVu Sans, sans-serif; font-size: 10px; white-space: pre-line; margin: 0; }

        .signature-box { border-top: 1px solid #333; margin-top: 40px; padding-top: 3px; font-size: 9px; text-align: center; width: 240px; }
        .footer-note { margin-top: 20px; padding-top: 6px; border-top: 1px solid #ccc; color: #555; font-size: 8.5px; text-align: center; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/logo.png');
        $logoDataUri = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
        $heading = $rental->status === \App\Models\Rental::STATUS_COTIZADA ? 'cotización' : 'confirmación de reserva';
    @endphp

    <table class="letterhead">
        <tr>
            @if ($logoDataUri)
                <td class="logo-cell"><img src="{{ $logoDataUri }}" alt="Sello CCH"></td>
            @endif
            <td class="brand">
                <strong>Cámara de Comercio</strong><br>
                y Producción de Huancayo<br>
                y la Región Junín
            </td>
            <td class="contact">
                Av. Giráldez N°634 – Huancayo – Perú<br>
                Cel: 915236568
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <h1 class="inst">CÁMARA DE COMERCIO DE HUANCAYO</h1>

    <div class="date-line">Huancayo, {{ now()->translatedFormat('d \\d\\e F \\d\\e Y') }}</div>

    <div class="salutation">Sres. {{ $rental->clientLabel() }}</div>

    <p class="lede">
        Reciba un cordial saludo a nombre del Consejo Directivo de la Cámara de Comercio de Huancayo y el mío
        propio; en el marco de nuestro compromiso con el desarrollo empresarial, le remito la {{ $heading }}
        correspondiente al servicio de alquiler del espacio de nuestra institución, esperando que sea de su
        interés y utilidad.
    </p>

    <table class="meta">
        <tr><th>Fechas</th><td>{{ ucfirst($rental->starts_at->translatedFormat('l d \\d\\e F')) }}</td></tr>
        <tr><th>Hora</th><td>{{ $rental->starts_at->format('g:i A') }} – {{ $rental->ends_at->format('g:i A') }}</td></tr>
        <tr><th>Evento</th><td>{{ $rental->purpose ?? '-' }}</td></tr>
        <tr><th>Locación</th><td>{{ $rental->space->name }}</td></tr>
    </table>

    <h2 class="section">Cotización de {{ $rental->space->name }}</h2>
    <table class="items">
        <thead>
        <tr>
            <th>Bienes de CCH</th>
            <th class="is-numeric">Cant./Horas</th>
            <th class="is-numeric">Precio/Hora</th>
            <th class="is-numeric">Precio Total</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>{{ $rental->space->name }}</td>
            <td class="is-numeric">{{ $rental->hours() }}</td>
            <td class="is-numeric">{{ $rental->space->default_rate ? format_money($rental->space->default_rate) : '-' }}</td>
            <td class="is-numeric">{{ format_money($rental->spaceSubtotal()) }}</td>
        </tr>
        @foreach ($rental->lineItems as $item)
            <tr>
                <td>{{ $item->label() }}</td>
                <td class="is-numeric">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                <td class="is-numeric">{{ $item->hourly_rate ? format_money($item->hourly_rate) : '-' }}</td>
                <td class="is-numeric">{{ $item->total() ? format_money($item->total()) : '-' }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="3">TOTAL</td>
            <td class="is-numeric">{{ format_money($rental->equipmentTotal()) }}</td>
        </tr>
        </tbody>
    </table>

    @if ($rental->catering)
        <h2 class="section" style="margin-top:16px;">Cotización coffee break</h2>
        <p style="margin: 0 0 6px;">
            Propuesta de coffee break{{ $rental->catering->people_count ? ' (para '.$rental->catering->people_count.' personas, puesto en mesa)' : '' }}:
        </p>
        <table class="meta">
            @if ($rental->catering->drink_option)
                <tr><th>Bebida</th><td>{{ $rental->catering->drink_option }}</td></tr>
            @endif
            @if ($rental->catering->sandwich_option)
                <tr><th>Sándwich</th><td>{{ $rental->catering->sandwich_option }}</td></tr>
            @endif
            @if ($rental->catering->dessert_option)
                <tr><th>Complemento dulce</th><td>{{ $rental->catering->dessert_option }}</td></tr>
            @endif
            @if ($rental->catering->daily_cost)
                <tr><th>Costo total por día</th><td>{{ format_money($rental->catering->daily_cost) }} (incluye IGV)</td></tr>
            @endif
        </table>
    @endif

    <h2 class="section">Términos y condiciones comerciales</h2>
    <ul class="legal-text">
        <li>Cualquier daño ocasionado a las instalaciones (paredes, servicios higiénicos u otros ambientes) y/o a los equipos contratados será asumido por el usuario, conforme a las condiciones y disposiciones establecidas por la Entidad.</li>
        <li>La Entidad aplica un cargo adicional por concepto de seguridad y vigilancia para eventos realizados los días sábados y domingos.</li>
        <li>La reserva de cualquiera de nuestros auditorios o zonas recreativas se realizará previa cancelación mínima del 50% del monto total cotizado, debiendo liquidarse el saldo restante como máximo un día antes del evento.</li>
    </ul>

    <div class="grand-total">
        Costo total del servicio: {{ format_money($rental->equipmentTotal()) }} (espacio y equipos)
        @if ($rental->catering)
            + {{ format_money($rental->cateringTotal()) }} (coffee break) = {{ format_money($rental->grandTotal()) }}
        @endif
    </div>

    @if ($rental->bank_account)
        <div class="payment">
            <h3>Modalidad de pago</h3>
            <pre>{{ $rental->bank_account }}</pre>
        </div>
    @endif

    <p>Agradezco de antemano su gentil atención y, sin otro particular, me es grato reiterarle mi consideración y estima.</p>
    <p>Atentamente.</p>

    <div class="signature-box">Logística y Alquileres</div>

    <div class="footer-note">
        "Haciendo empresa, hacemos Perú" — Av. Giráldez N°634 – Huancayo – Perú — Cel: 915236568
    </div>
</body>
</html>
