<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }
        .letterhead { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .letterhead td { border: none; vertical-align: top; }
        .letterhead .brand { font-size: 9px; font-weight: bold; line-height: 1.3; }
        .letterhead h1 { font-size: 15px; text-align: center; margin: 4px 0 0; }
        .letterhead .meta { text-align: right; font-size: 9px; }
        .letterhead .meta .box { border: 1px solid #333; display: inline-block; padding: 2px 8px; margin-left: 4px; }

        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.grid th, table.grid td { border: 1px solid #333; padding: 3px 5px; font-size: 9.5px; vertical-align: top; }
        .section-title { background: #1a1a1a; color: #fff; font-weight: bold; padding: 3px 6px; font-size: 10px; text-transform: uppercase; }
        .field-label { font-weight: bold; display: block; font-size: 8.5px; color: #444; }
        .checkbox { display: inline-block; width: 8px; height: 8px; border: 1px solid #333; margin-right: 3px; text-align: center; line-height: 8px; font-size: 8px; }
        .center { text-align: center; }
    </style>
</head>
<body>

    <table class="letterhead">
        <tr>
            <td style="width: 30%;" class="brand">CÁMARA DE COMERCIO<br>DE HUANCAYO</td>
            <td style="width: 40%;"><h1>FICHA DE INSCRIPCIÓN</h1></td>
            <td style="width: 30%;" class="meta">
                Huancayo, {{ now()->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <table class="grid">
        <tr><td colspan="4" class="section-title">Datos de la empresa</td></tr>
        <tr>
            <td style="width:70%;"><span class="field-label">Razón Social</span>{{ $associate->name }}</td>
            <td style="width:30%;"><span class="field-label">RUC</span>{{ $associate->ruc ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">Nombre Comercial</span>{{ $associate->company ?? '-' }}</td>
            <td><span class="field-label">Fecha de inicio de Actividades</span>{{ $associate->activities_started_at?->format('d/m/Y') ?? '-' }}</td>
        </tr>
        <tr>
            <td colspan="2"><span class="field-label">Dirección</span>{{ $associate->billing_address ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">Distrito</span>{{ $associate->billing_district ?? '-' }}</td>
            <td><span class="field-label">Provincia</span>{{ $associate->billing_province ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">Dirección para correspondencia</span>{{ $associate->mailing_address ?? '-' }}</td>
            <td><span class="field-label">Distrito de correspondencia</span>{{ $associate->mailing_district ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">Teléfono</span>{{ $associate->contact_phone ?? '-' }}</td>
            <td><span class="field-label">Página web</span>{{ $associate->website ?? '-' }}</td>
        </tr>
        <tr>
            <td colspan="2"><span class="field-label">E-mail</span>{{ $associate->email ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">Inscripción Registros Públicos — Partida Elect. N°</span>{{ $associate->public_registry_entry ?? '-' }}</td>
            <td><span class="field-label">Título</span>{{ $associate->public_registry_title ?? '-' }}</td>
        </tr>
        <tr>
            <td colspan="2"><span class="field-label">Observ.</span>{{ $associate->notes ?? '-' }}</td>
        </tr>
    </table>

    <table class="grid">
        <tr><td colspan="4" class="section-title">Presentación de la empresa</td></tr>
        <tr>
            <td style="width:55%;"><span class="field-label">Representante Legal</span>{{ $associate->legal_rep_name ?? '-' }}</td>
            <td style="width:20%;"><span class="field-label">DNI</span>{{ $associate->legal_rep_dni ?? '-' }}</td>
            <td style="width:25%;"><span class="field-label">Cargo</span>{{ $associate->legal_rep_position ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">Teléfono / E-mail</span>{{ $associate->legal_rep_phone ?? '-' }} {{ $associate->legal_rep_email ? '/ '.$associate->legal_rep_email : '' }}</td>
            <td colspan="2"><span class="field-label">Fecha de Nacimiento</span>{{ $associate->legal_rep_birthday?->format('d/m/Y') ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">Representante ante la Cámara</span>{{ $associate->cch_rep_name ?? '-' }}</td>
            <td><span class="field-label">DNI</span>{{ $associate->cch_rep_dni ?? '-' }}</td>
            <td><span class="field-label">Cargo</span>{{ $associate->cch_rep_position ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">Teléfono / E-mail</span>{{ $associate->cch_rep_phone ?? '-' }} {{ $associate->cch_rep_email ? '/ '.$associate->cch_rep_email : '' }}</td>
            <td colspan="2"><span class="field-label">Fecha de Nacimiento</span>{{ $associate->cch_rep_birthday?->format('d/m/Y') ?? '-' }}</td>
        </tr>
    </table>

    <table class="grid">
        <tr><td colspan="4" class="section-title">Principales ejecutivos</td></tr>
        <tr>
            <th>Apellidos y Nombres</th>
            <th>Cargo</th>
            <th>Teléfono</th>
            <th>Fecha de Nacimiento</th>
        </tr>
        @forelse ($associate->executives as $executive)
            <tr>
                <td>{{ $executive->name }}</td>
                <td>{{ $executive->position ?? '-' }}</td>
                <td>{{ $executive->phone ?? '-' }}</td>
                <td>{{ $executive->birthday?->format('d/m/Y') ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">&nbsp;</td></tr>
        @endforelse
    </table>

    <table class="grid">
        <tr><td colspan="8" class="section-title">Actividad, sector y productos</td></tr>
        <tr>
            <td style="width:25%;"><span class="field-label">Actividad Principal</span>{{ $associate->main_activity ?? '-' }}</td>
            <td style="width:35%;"><span class="field-label">Actividades Complementarias</span>{{ $associate->complementary_activities ? implode(', ', $associate->complementary_activities) : '-' }}</td>
            <td style="width:20%;"><span class="field-label">CIIU</span>{{ $associate->ciiu ?? '-' }}</td>
            <td style="width:20%;"><span class="field-label">Profesión</span>{{ $associate->profession ?? '-' }}</td>
        </tr>
    </table>

    @if ($associate->products->isNotEmpty())
        <table class="grid">
            <tr>
                <th style="width:40%;">Producto / Servicio</th>
                <th class="center">F</th>
                <th class="center">P</th>
                <th class="center">C</th>
                <th class="center">I</th>
                <th class="center">S</th>
                <th class="center">E</th>
            </tr>
            @foreach ($associate->products as $product)
                <tr>
                    <td>{{ $product->description }}</td>
                    <td class="center">{{ $product->is_fabrica ? 'X' : '' }}</td>
                    <td class="center">{{ $product->is_produce ? 'X' : '' }}</td>
                    <td class="center">{{ $product->is_comercializa ? 'X' : '' }}</td>
                    <td class="center">{{ $product->is_importa ? 'X' : '' }}</td>
                    <td class="center">{{ $product->is_servicios ? 'X' : '' }}</td>
                    <td class="center">{{ $product->is_exporta ? 'X' : '' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

</body>
</html>
