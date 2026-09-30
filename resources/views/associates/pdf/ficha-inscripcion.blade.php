<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 26px 34px 52px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }

        .footer {
            position: fixed; bottom: -32px; left: 0; width: 100%;
            background: #0F2747; color: #fff; text-align: center;
            font-size: 7.5px; padding: 5px 0; letter-spacing: 0.2px;
        }

        .letterhead { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .letterhead td { border: none; vertical-align: middle; }
        .letterhead .logo-cell { width: 58px; }
        .letterhead .logo-cell img { width: 52px; height: 52px; }
        .letterhead .brand { font-size: 9px; font-weight: bold; line-height: 1.35; color: #0F2747; }
        .letterhead h1 { font-size: 15px; text-align: center; margin: 2px 0 0; color: #0F2747; letter-spacing: 0.3px; }
        .letterhead h2 { font-size: 8.5px; text-align: center; margin: 2px 0 0; color: #14B8A6; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .letterhead .meta { text-align: right; font-size: 9px; color: #0F2747; }
        .letterhead .meta .box { border: 1px solid #0F2747; display: inline-block; padding: 2px 8px; margin-left: 4px; font-weight: bold; }
        .letterhead-rule { height: 3px; background: #14B8A6; margin: 6px 0 8px; }

        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.grid th, table.grid td { border: 1px solid #ccd3dc; padding: 3px 5px; font-size: 9.5px; vertical-align: top; }
        .section-title { background: #0F2747; color: #fff; font-weight: bold; padding: 4px 6px; font-size: 10px; text-transform: uppercase; border: 1px solid #0F2747; }
        .field-label { font-weight: bold; display: block; font-size: 8.5px; color: #444; }
        .checkbox { display: inline-block; width: 8px; height: 8px; border: 1px solid #333; margin-right: 3px; text-align: center; line-height: 8px; font-size: 8px; }
        .center { text-align: center; }
        .legal-text { font-size: 8.5px; line-height: 1.5; text-align: justify; }
        .signature-box { border-top: 1px solid #333; margin-top: 28px; padding-top: 2px; font-size: 8.5px; text-align: center; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>

    <div class="footer">
        Av. Giraldez N&deg; 634 Huancayo - Per&uacute; &nbsp;|&nbsp; Telefax: (064) 231432 / 211226 &nbsp;|&nbsp; www.camarahuancayo.org.pe
    </div>

    <table class="letterhead">
        <tr>
            <td class="logo-cell"><img src="{{ $logoDataUri }}" alt="Sello CCH"></td>
            <td style="width: 27%;" class="brand">CÁMARA DE COMERCIO<br>Y PRODUCCIÓN DE HUANCAYO<br>Y LA REGIÓN JUNÍN</td>
            <td style="width: 36%;">
                <h1>FICHA DE AFILIACIÓN</h1>
                <h2>Ficha de Inscripción</h2>
            </td>
            <td style="width: 27%;" class="meta">
                <span class="field-label" style="display:inline; color:#0F2747;">Código</span> <span class="box">{{ $associate->internal_code ?? '' }}</span><br>
                Huancayo, {{ now()->format('d/m/Y') }}
            </td>
        </tr>
    </table>
    <div class="letterhead-rule"></div>

    <table class="grid">
        <tr><td colspan="4" class="section-title">Datos de la empresa</td></tr>
        <tr>
            <td style="width:70%;"><span class="field-label">Razón Social</span>{{ $associate->name }}</td>
            <td style="width:30%;"><span class="field-label">RUC</span>{{ $associate->ruc ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">Nombre Comercial</span>{{ $associate->company ?? '-' }}</td>
            <td><span class="field-label">Fecha de Aniversario</span>{{ $associate->anniversary_date?->format('d/m/Y') ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">Dirección</span>{{ $associate->billing_address ?? '-' }} {{ $associate->address_number ? 'N° '.$associate->address_number : '' }} {{ $associate->address_lot_interior ? '— '.$associate->address_lot_interior : '' }}</td>
            <td><span class="field-label">Distrito / Provincia</span>{{ $associate->billing_district ?? '-' }} / {{ $associate->billing_province ?? '-' }}</td>
        </tr>
        @if ($associate->address_reference)
            <tr>
                <td colspan="2"><span class="field-label">Referencia (Croquis)</span>{{ $associate->address_reference }}</td>
            </tr>
        @endif
        <tr>
            <td><span class="field-label">Dirección para correspondencia</span>{{ $associate->mailing_address ?? '-' }}</td>
            <td><span class="field-label">Distrito de correspondencia</span>{{ $associate->mailing_district ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">Teléfono / Fax</span>{{ $associate->contact_phone ?? '-' }} {{ $associate->fax ? '/ '.$associate->fax : '' }}</td>
            <td><span class="field-label">Celular</span>{{ $associate->mobile_phone ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">E-mail</span>{{ $associate->email ?? '-' }}</td>
            <td><span class="field-label">Página web</span>{{ $associate->website ?? '-' }}</td>
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
        <tr><td colspan="5" class="section-title">Principales ejecutivos</td></tr>
        <tr>
            <th>Apellidos y Nombres</th>
            <th>Cargo</th>
            <th>Teléfono</th>
            <th>E-mail</th>
            <th>Fecha de Nacimiento</th>
        </tr>
        @forelse ($associate->executives as $executive)
            <tr>
                <td>{{ $executive->name }}</td>
                <td>{{ $executive->position ?? '-' }}</td>
                <td>{{ $executive->phone ?? '-' }}</td>
                <td>{{ $executive->email ?? '-' }}</td>
                <td>{{ $executive->birthday?->format('d/m/Y') ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="5">&nbsp;</td></tr>
        @endforelse
    </table>

    <table class="grid">
        <tr><td colspan="8" class="section-title">Actividad, sector y productos</td></tr>
        <tr>
            <td style="width:25%;"><span class="field-label">Actividad Principal</span>{{ $associate->main_activity ?? '-' }}</td>
            <td style="width:30%;"><span class="field-label">Actividades Complementarias</span>{{ $associate->complementary_activities ? implode(', ', $associate->complementary_activities) : '-' }}</td>
            <td style="width:25%;"><span class="field-label">Sector Económico</span>{{ $associate->sector_economico ?? '-' }}</td>
            <td style="width:20%;"><span class="field-label">CIIU</span>{{ $associate->ciiu ?? '-' }}</td>
        </tr>
        <tr>
            <td colspan="4"><span class="field-label">Profesión del representante legal</span>{{ $associate->profession ?? '-' }}</td>
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

    <div class="page-break"></div>

    <table class="grid">
        <tr><td colspan="4" class="section-title">Información para uso interno — perfil del negocio</td></tr>
        <tr>
            <td colspan="2"><span class="field-label">Principales insumos que demanda</span>{{ $associate->main_inputs ?? '-' }}</td>
            <td colspan="2"><span class="field-label">Principales proveedores y clientes</span>{{ $associate->main_suppliers ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="field-label">N° de Trabajadores</span>{{ $associate->employee_count_range ?? '-' }}</td>
            <td><span class="field-label">Patrimonio (miles S/.)</span>{{ $associate->assets_range ?? '-' }}</td>
            <td><span class="field-label">Ventas mensuales (S/.)</span>{{ $associate->monthly_sales_range ?? '-' }}</td>
            <td><span class="field-label">Ventas anuales (miles S/.)</span>{{ $associate->annual_sales_range ?? '-' }}</td>
        </tr>
        <tr>
            <td colspan="2"><span class="field-label">Asociaciones gremiales a las que pertenece</span>{{ $associate->trade_associations ? implode(', ', $associate->trade_associations) : '-' }}</td>
            <td colspan="2"><span class="field-label">Servicios que le interesó para afiliarse</span>{{ $associate->interested_services ?? '-' }}</td>
        </tr>
    </table>

    <table class="grid">
        <tr><td class="section-title">Declaración jurada</td></tr>
        <tr>
            <td class="legal-text">
                Declaramos que la información que figura en esta solicitud expresa la verdad, y nos comprometemos a cumplir con el pago de las cuotas
                mensuales asignadas a nuestra empresa. A su vez declaramos que tenemos conocimiento que la única condición válida para dejar de
                pertenecer a la Cámara de Comercio de Huancayo y por consiguiente dejar de pagar la cuota institucional será con el envío de una carta
                (físicamente) dirigida a la Gerencia General conteniendo firma y sello del representante ante la institución. En caso de mantener más
                de 4 cuotas impagas, autorizamos a la CCH a reportar esta deuda a las Centrales de Riesgo. Asimismo, nos comprometemos a actualizar
                los datos de la empresa semestralmente o cuando se considere necesario. El tiempo mínimo de permanencia como asociado será de 6
                (seis) meses calendarios, caso contrario se tomarán las medidas indicadas en el estatuto de la institución. El uso de la información
                brindada se desarrollará en el marco de la Ley N° 29733.
            </td>
        </tr>
        <tr>
            <td>
                <table style="width:100%; border-collapse: collapse;">
                    <tr>
                        <td style="width:40%; border: none;"><div class="signature-box">Nombre del Representante Legal de la Empresa<br>{{ $associate->legal_rep_name ?? '' }}</div></td>
                        <td style="width:30%; border: none;"><div class="signature-box">Cargo<br>{{ $associate->legal_rep_position ?? '' }}</div></td>
                        <td style="width:30%; border: none;"><div class="signature-box">Firma y Sello (Opcional)</div></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="grid">
        <tr><td colspan="4" class="section-title">Datos para ser llenados por la Cámara de Comercio de Huancayo</td></tr>
        <tr>
            <td><span class="field-label">Categoría</span>{{ $associate->category ?? '-' }}</td>
            <td><span class="field-label">Cuota de inscripción</span>{{ $associate->registration_fee ? format_money($associate->registration_fee) : '-' }}</td>
            <td><span class="field-label">Cuota Mensual</span>{{ $associate->monthly_fee ? format_money($associate->monthly_fee) : '-' }}</td>
            <td><span class="field-label">Cuota Anual</span>{{ $associate->annual_fee ? format_money($associate->annual_fee) : '-' }}</td>
        </tr>
        <tr>
            <td colspan="4"><span class="field-label">Pago de la cuota de inscripción realizado en</span>{{ $associate->registration_payment_method ? ucfirst(mb_strtolower($associate->registration_payment_method, 'UTF-8')) : '-' }}</td>
        </tr>
        <tr>
            <td colspan="4">
                <table style="width:100%; border-collapse: collapse;">
                    <tr>
                        <td style="width:33%; border: none;"><div class="signature-box">Gerente General</div></td>
                        <td style="width:33%; border: none;"><div class="signature-box">Secretario de la Comisión</div></td>
                        <td style="width:34%; border: none;"><div class="signature-box">Presidente de la Comisión</div></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
