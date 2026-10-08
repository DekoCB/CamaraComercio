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

        h1.inst { font-size: 13px; text-align: center; margin: 0 0 2px; letter-spacing: .02em; }
        h2.doc-number { font-size: 11px; text-align: right; margin: 0 0 14px; color: #0F2747; }

        table.heading-meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.heading-meta td { border: none; padding: 1px 0; font-size: 10px; vertical-align: top; }
        table.heading-meta td.label { width: 60px; font-weight: bold; }

        p.lede { text-align: justify; line-height: 1.5; margin-bottom: 10px; }

        h2.section { font-size: 11px; text-transform: uppercase; margin: 14px 0 6px; color: #0F2747; }
        .payment pre { font-family: DejaVu Sans, sans-serif; font-size: 10px; white-space: pre-line; margin: 4px 0 0; }

        table.items { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.items th { background: #0F2747; color: #fff; padding: 4px 6px; font-size: 8.5px; text-transform: uppercase; text-align: left; }
        table.items th.is-numeric, table.items td.is-numeric { text-align: right; }
        table.items td { border: 1px solid #ccd3dc; padding: 4px 6px; font-size: 9px; }
        table.items tr:nth-child(even) td { background: #F0F4F8; }
        table.items tr.total td { background: #0F2747; color: #fff; font-weight: bold; }

        .signature-box { border-top: 1px solid #333; margin: 90px auto 0; padding-top: 3px; font-size: 9px; text-align: center; width: 280px; }
        .footer-note { margin-top: 20px; padding-top: 6px; border-top: 1px solid #ccc; color: #555; font-size: 8.5px; text-align: center; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/logo.png');
        $logoDataUri = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
        $isReembolso = $requisition->type === \App\Models\PaymentRequisition::TYPE_REEMBOLSO;
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
    <h2 class="doc-number">REQUERIMIENTO N° {{ $requisition->documentNumber() }}</h2>

    <table class="heading-meta">
        <tr>
            <td class="label">A</td>
            <td>
                : {{ $requisition->recipient_name }}
                @if ($requisition->recipient_role)
                    <br>&nbsp;&nbsp;{{ $requisition->recipient_role }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">DE</td>
            <td>
                : {{ $requisition->creator->name ?? '-' }}
                @if ($requisition->requester_area)
                    <br>&nbsp;&nbsp;{{ $requisition->requester_area }}
                @endif
            </td>
        </tr>
        <tr><td class="label">ASUNTO</td><td>: {{ $requisition->subject }}</td></tr>
        <tr><td class="label">FECHA</td><td>: {{ $requisition->issued_at->translatedFormat('d \\d\\e F \\d\\e Y') }}</td></tr>
    </table>

    @if ($isReembolso)
        <p class="lede">
            Por medio de la presente, se solicita realizar el reembolso correspondiente por un monto total de
            <strong>{{ format_money($requisition->total()) }}</strong> a favor de <strong>{{ $requisition->beneficiary_name }}</strong>,
            por concepto de {{ mb_strtolower($requisition->subject, 'UTF-8') }}.
        </p>
        <p class="lede">
            Los comprobantes de pago de ley y el detalle específico de lo adquirido se encuentran sustentados en el cuadro adjunto.
        </p>
    @else
        <p class="lede">
            Por medio de la presente, se solicita autorizar el abono correspondiente por un monto total de
            <strong>{{ format_money($requisition->total()) }}</strong> a favor del proveedor <strong>{{ $requisition->beneficiary_name }}</strong>{{ $requisition->provider_ruc ? ', con RUC '.$requisition->provider_ruc : '' }},
            por concepto de {{ mb_strtolower($requisition->subject, 'UTF-8') }}.
        </p>
        <p class="lede">
            El bien o servicio detallado se encuentra especificado en el cuadro adjunto.
        </p>
    @endif

    @if ($requisition->bank_details)
        <h2 class="section">Se solicita el {{ $isReembolso ? 'reembolso' : 'desembolso' }} de lo mencionado a</h2>
        <div class="payment">
            <strong>Titular:</strong> {{ $requisition->beneficiary_name }}
            <pre>{{ $requisition->bank_details }}</pre>
        </div>
    @endif

    <h2 class="section">Detalle</h2>
    <table class="items">
        <thead>
        <tr>
            <th>N°</th>
            <th>Fecha</th>
            <th>N° comprobante/operación</th>
            <th>Descripción</th>
            <th class="is-numeric">Cant.</th>
            <th class="is-numeric">Pr. unitario</th>
            <th class="is-numeric">Importe</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($requisition->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->item_date ? $item->item_date->format('d/m/Y') : '-' }}</td>
                <td>{{ $item->reference ?? '-' }}</td>
                <td>{{ $item->description }}</td>
                <td class="is-numeric">{{ $item->quantity ?? '-' }}</td>
                <td class="is-numeric">{{ $item->unit_price ? format_money($item->unit_price) : '-' }}</td>
                <td class="is-numeric">{{ format_money($item->amount) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="6">TOTAL</td>
            <td class="is-numeric">{{ format_money($requisition->total()) }}</td>
        </tr>
        </tbody>
    </table>

    @if ($requisition->notes)
        <p class="lede" style="margin-top: 10px;">{{ $requisition->notes }}</p>
    @endif

    <p class="lede" style="margin-top: 14px;">Sin otro particular, se solicita la atención y autorización correspondiente.</p>
    <p class="lede">Atentamente,</p>

    <div class="signature-box">
        {{ $requisition->requester_area ?? 'Logística y Operaciones' }}<br>
        {{ $requisition->creator->name ?? '' }}
    </div>

    <div class="footer-note">
        "Haciendo empresa, hacemos Perú" — Av. Giráldez N°634 – Huancayo – Perú — Cel: 915236568
    </div>
</body>
</html>
