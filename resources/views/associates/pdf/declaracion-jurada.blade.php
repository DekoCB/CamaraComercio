<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.6; }
        .center { text-align: center; }
        h1 { font-size: 14px; text-align: center; margin: 4px 0 20px; }
        .brand { text-align: center; font-style: italic; font-size: 11px; margin-bottom: 4px; }
        .fill { border-bottom: 1px solid #333; padding: 0 2px; }
        p { margin: 0 0 10px; text-align: justify; }
        ol.clauses { margin: 0 0 14px; padding-left: 18px; }
        ol.clauses li { margin-bottom: 6px; }
        .date-line { margin: 24px 0 40px; }
        table.signatures { width: 100%; margin-top: 30px; }
        table.signatures td { vertical-align: bottom; text-align: center; padding: 0 10px; }
        .signature-box, .fingerprint-box { border: 1px solid #333; height: 70px; display: flex; align-items: center; justify-content: center; }
        .signature-box img, .fingerprint-box img { max-height: 65px; max-width: 100%; }
        .sig-line { border-top: 1px solid #333; margin-top: 6px; padding-top: 4px; }
    </style>
</head>
<body>

    <div class="brand">Cámara de Comercio de Huancayo</div>
    <h1>DECLARACIÓN JURADA</h1>

    <p>
        Yo, <span class="fill">{{ $declarantName }}</span>, identificado con DNI N° <span class="fill">{{ $declarantDni ?: '—' }}</span>,
        con domicilio en <span class="fill">{{ $associate->billing_address ?: '—' }}</span>,
        del distrito de <span class="fill">{{ $associate->billing_district ?: '—' }}</span>, provincia <span class="fill">{{ $associate->billing_province ?: '—' }}</span>,
        departamento de <span class="fill">{{ $associate->billing_department ?: '—' }}</span>, en mi calidad de
        {{ $isPersonaNatural ? 'persona natural' : 'representante legal de la empresa' }}
        <span class="fill">{{ $associate->name }}</span>, con
        RUC N° <span class="fill">{{ $associate->ruc ?: '—' }}</span>, en mi calidad de <strong>{{ $membershipLabel }}</strong> de la Cámara de
        Comercio de Huancayo (CCH) en cumplimiento a lo señalado en el art. 7.3° del estatuto,
        DECLARO BAJO JURAMENTO lo siguiente:
    </p>

    <ol class="clauses" type="a">
        <li>Desarrollar actividades empresariales lícitas enmarcadas en la normatividad legal peruana.</li>
        <li>Tener solvencia moral y ética empresarial, sujetándome a los lineamientos señalados en su estatuto, código de ética y demás normas internas de la CCH.</li>
        <li>No tener procesos judiciales en curso con la CCH.</li>
        <li>No tener ninguna deuda con la CCH.</li>
        <li>No estar declarado en quiebra.</li>
        <li>No haber realizado actos dolosos y/o actos que dañen la imagen y/o patrimonio de la CCH.</li>
        <li>No estar involucrado en actos de corrupción, narcotráfico, lavado de activos o actos dolosos que dañen la imagen de la CCH.</li>
    </ol>

    <p>
        En caso de incumplimiento de lo señalado líneas arriba autorizo a la CCH que de manera automática proceda con mi
        desafiliación como asociado y me someto a las denuncias que corresponda.
    </p>

    <div class="date-line">
        Huancayo, <span class="fill">{{ $declarationDate->format('d') }}</span> de
        <span class="fill">{{ $declarationDate->translatedFormat('F') }}</span> de <span class="fill">{{ $declarationDate->format('Y') }}</span>
    </div>

    <table class="signatures">
        <tr>
            <td style="width: 55%;">
                <div class="signature-box">
                    @if ($signatureDataUri)
                        <img src="{{ $signatureDataUri }}" alt="Firma">
                    @endif
                </div>
                <div class="sig-line">Firma — DNI N° {{ $declarantDni ?: '—' }}</div>
            </td>
            <td style="width: 30%;">
                <div class="fingerprint-box">
                    @if ($fingerprintDataUri)
                        <img src="{{ $fingerprintDataUri }}" alt="Huella digital">
                    @endif
                </div>
                <div class="sig-line">Huella Digital</div>
            </td>
        </tr>
    </table>

    @if (! $signatureDataUri || ! $fingerprintDataUri)
        <p style="margin-top: 24px; font-size: 9px; color: #666;">
            Documento para imprimir y firmar a mano{{ $signatureDataUri || $fingerprintDataUri ? ' — falta completar la firma o la huella digital.' : '.' }}
        </p>
    @endif

</body>
</html>
