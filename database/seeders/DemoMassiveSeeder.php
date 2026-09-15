<?php

namespace Database\Seeders;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Large, realistic development dataset for every module — hundreds of
 * associates with the full master-data sheet, ~3 years of monthly
 * invoicing, payments across every channel (with a few voids), extra
 * collector users and enough birthdays around today to exercise the
 * calendar and its notifications.
 *
 *   php artisan db:seed --class=DemoMassiveSeeder
 *
 * Safe to re-run: demo associates are recognisable by their e-mail
 * domain, so a second run is skipped instead of duplicating them. To
 * start over: php artisan migrate:fresh --seed && php artisan db:seed
 * --class=DemoMassiveSeeder. Never meant for production.
 */
class DemoMassiveSeeder extends Seeder
{
    public const DEMO_DOMAIN = 'demo.camaracomercio.test';

    private const ASSOCIATES = 320;

    private const FIRST_PERIOD = '2024-01';

    private const SECTORISTAS = ['ROSA', 'CARMEN', 'LUIS', 'MARÍA', 'JORGE'];

    private const CATEGORY_FEES = ['A' => 200.00, 'B' => 150.00, 'C' => 100.00, 'D' => 75.00, 'E' => 50.00];

    private const DISTRICTS = ['HUANCAYO', 'EL TAMBO', 'CHILCA', 'PILCOMAYO', 'SICAYA', 'SAN AGUSTÍN DE CAJAS', 'HUANCÁN', 'JAUJA', 'CONCEPCIÓN', 'CHUPACA', 'SAPALLANGA', 'SAN JERÓNIMO DE TUNÁN'];

    private const STREETS = ['JR. HUANCAS', 'AV. GIRÁLDEZ', 'CALLE REAL', 'AV. FERROCARRIL', 'JR. ANCASH', 'AV. HUANCAVELICA', 'JR. CUSCO', 'AV. MARISCAL CASTILLA', 'JR. LIMA', 'AV. LEONCIO PRADO', 'PSJE. SAN CARLOS', 'AV. EVITAMIENTO', 'JR. AREQUIPA', 'AV. LOS INCAS'];

    private const COMMITTEES = ['COMERCIO', 'SERVICIOS', 'INDUSTRIA Y MANUFACTURA', 'CONSTRUCCION E INMOBILIARIA / SALUD', 'MINERIA, PETROLEO Y ENERGIA', 'TURISMO Y GASTRONOMIA', 'EDUCACION', 'TRANSPORTE Y LOGISTICA', 'AGROINDUSTRIA', 'TECNOLOGIA Y COMUNICACIONES', 'FINANZAS Y SEGUROS'];

    private const CIIU = ['VENTA AL POR MAYOR DE ALIMENTOS, BEBIDAS Y TABACO', 'VENTA AL POR MENOR EN COMERCIOS NO ESPECIALIZADOS', 'ACTIVIDADES DE MÉDICOS Y ODONTÓLOGOS', 'ACTIVIDADES DE ARQUITECTURA E INGENIERÍA', 'FABRICACIÓN DE CEMENTO, CAL Y YESO', 'ELABORACIÓN DE PRODUCTOS DE PANADERÍA', 'ACTIVIDADES DE RESTAURANTES Y DE SERVICIO MÓVIL DE COMIDAS', 'TRANSPORTE DE CARGA POR CARRETERA', 'ENSEÑANZA PRIMARIA Y SECUNDARIA', 'ACTIVIDADES DE CONSULTORÍA DE GESTIÓN', 'CONSTRUCCIÓN DE EDIFICIOS', 'VENTA DE VEHÍCULOS AUTOMOTORES', 'ACTIVIDADES JURÍDICAS', 'ACTIVIDADES DE CONTABILIDAD Y AUDITORÍA', 'ACTIVIDADES DE HOSPEDAJE', 'FABRICACIÓN DE MUEBLES', 'CULTIVO DE HORTALIZAS Y TUBÉRCULOS', 'ACTIVIDADES DE PROGRAMACIÓN INFORMÁTICA', 'VENTA AL POR MENOR DE PRODUCTOS FARMACÉUTICOS', 'EXTRACCIÓN DE MINERALES METALÍFEROS'];

    private const COMPANY_A = ['Comercial', 'Distribuidora', 'Inversiones', 'Corporación', 'Grupo', 'Importadora', 'Constructora', 'Consultora', 'Transportes', 'Agroindustrias', 'Servicios', 'Negocios', 'Ferretería', 'Botica', 'Panadería', 'Restaurante', 'Hotel', 'Clínica', 'Colegio', 'Minera', 'Textiles', 'Multiservicios', 'Representaciones', 'Ingeniería', 'Tecnologías'];

    private const COMPANY_B = ['Andina', 'Wanka', 'del Centro', 'Huancayo', 'Mantaro', 'Los Andes', 'Junín', 'San Carlos', 'El Tambo', 'Perú', 'Continental', 'Sierra Central', 'Real', 'Santa Rosa', 'San Martín', 'Valle', 'Inca', 'Sol Naciente', 'Nuevo Horizonte', 'Progreso', 'Unión', 'Esperanza', 'Libertad', 'Victoria', 'Sumaq', 'Kuntur', 'Pachamama', 'Tayta', 'Yauri', 'Chanchamayo'];

    private const SUFFIX = ['S.A.C.', 'S.A.C.', 'S.A.C.', 'E.I.R.L.', 'E.I.R.L.', 'S.R.L.', 'S.A.'];

    private const FIRST_NAMES_M = ['JUAN', 'CARLOS', 'LUIS', 'JOSÉ', 'MIGUEL', 'JORGE', 'PEDRO', 'MARIO', 'RAÚL', 'VÍCTOR', 'WALTER', 'RICARDO', 'FERNANDO', 'DANIEL', 'ANDRÉS', 'JAVIER', 'OSCAR', 'EDGAR', 'HUGO', 'ALBERTO', 'RUBÉN', 'FREDY', 'WILLIAM', 'ELVIS', 'JHONNY'];

    private const FIRST_NAMES_F = ['MARÍA', 'ROSA', 'CARMEN', 'ANA', 'LUZ', 'ELIZABETH', 'GLADYS', 'NANCY', 'PATRICIA', 'SILVIA', 'ROCÍO', 'VERÓNICA', 'YOLANDA', 'MARTHA', 'JULIA', 'ISABEL', 'ANGÉLICA', 'ROSARIO', 'LILIANA', 'KARINA', 'YESENIA', 'MARIBEL', 'ZULEMA', 'DORIS', 'FLOR'];

    private const SURNAMES = ['QUISPE', 'HUAMÁN', 'MAMANI', 'FLORES', 'SÁNCHEZ', 'RODRÍGUEZ', 'GARCÍA', 'ROJAS', 'TORRES', 'CASTRO', 'VARGAS', 'RAMOS', 'PÉREZ', 'GUTIÉRREZ', 'DÍAZ', 'ESPINOZA', 'CÁRDENAS', 'MEZA', 'BALDEÓN', 'SAPAICO', 'INGA', 'POMA', 'CHÁVEZ', 'ORÉ', 'CÓRDOVA', 'PAUCAR', 'YUPANQUI', 'ARAUCO', 'LAZO', 'VILA', 'CANCHARI', 'SOLÍS', 'ALIAGA', 'CAMARENA', 'HINOSTROZA'];

    private const NOTES = ['Paga siempre a inicio de mes.', 'Prefiere que se le contacte por WhatsApp.', 'Solicita factura a nombre de la matriz.', 'Cambió de representante legal en 2025.', 'Pagos suelen atrasarse en temporada baja.', 'Participa activamente en el comité sectorial.', 'Requiere copia del comprobante por correo.', 'Empresa familiar, tercera generación.'];

    private const PAYMENT_NOTES = ['Pago en ventanilla', 'Transferencia BCP', 'Depósito Interbank', 'Yape al número de la Cámara', 'Pago con voucher físico', 'Abono parcial acordado', 'Regularización de cuota', 'Pago realizado por el representante'];

    private const METHOD_WEIGHTS = ['EFECTIVO' => 35, 'TRANSFERENCIA' => 25, 'YAPE' => 20, 'PLIN' => 8, 'DEPOSITO' => 7, 'TARJETA' => 3, 'CHEQUE' => 1, 'OTRO' => 1];

    public function run(): void
    {
        if (Associate::where('email', 'like', '%@'.self::DEMO_DOMAIN)->exists()) {
            $this->command->warn('DemoMassiveSeeder: los datos demo ya existen, no se duplican. Use migrate:fresh --seed para empezar de cero.');

            return;
        }

        mt_srand(20260915); // reproducible dataset
        $today = CarbonImmutable::today();

        $users = $this->seedUsers();
        $this->command->info('Usuarios: '.count($users).' encargados de cobranza adicionales.');

        $associateIds = $this->seedAssociates($today);
        $this->command->info('Asociados: '.count($associateIds).' creados con ficha completa.');

        [$invoiceCount, $paymentCount, $voidCount] = $this->seedBilling($associateIds, $users, $today);
        $this->command->info("Facturas: {$invoiceCount} · Pagos: {$paymentCount} (de ellos {$voidCount} anulados y repuestos).");

        $this->command->info('Listo. Contraseña de los usuarios demo: Demo#2026');
    }

    /** @return int[] user ids that register payments */
    private function seedUsers(): array
    {
        $role = Role::where('name', 'Encargado de Cobranzas')->firstOrFail();
        $ids = [User::where('email', 'admin@camaracomercio.test')->value('id') ?? User::orderBy('id')->value('id')];

        foreach ([['Rosa Meza Inga', 'rosa'], ['Carmen Baldeón Lazo', 'carmen'], ['Luis Paucar Ore', 'luis'], ['María Camarena Vila', 'maria'], ['Jorge Aliaga Solís', 'jorge']] as [$name, $username]) {
            $user = User::updateOrCreate(
                ['email' => $username.'@'.self::DEMO_DOMAIN],
                ['name' => $name, 'username' => $username, 'password' => Hash::make('Demo#2026'), 'role_id' => $role->id, 'is_active' => true, 'email_verified_at' => now()]
            );
            $ids[] = $user->id;
        }

        return $ids;
    }

    /** @return array<int, array{id: int, fee: float, joined: CarbonImmutable, status: string}> */
    private function seedAssociates(CarbonImmutable $today): array
    {
        $rows = [];
        $usedRuc = [];
        $usedNames = [];
        $now = now();

        for ($i = 1; $i <= self::ASSOCIATES; $i++) {
            do {
                $name = $this->pick(self::COMPANY_A).' '.$this->pick(self::COMPANY_B);
            } while (isset($usedNames[$name]));
            $usedNames[$name] = true;
            $suffix = $this->pick(self::SUFFIX);
            $legal = mb_strtoupper($name).' '.$suffix;

            do {
                $ruc = '20'.str_pad((string) mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
            } while (isset($usedRuc[$ruc]));
            $usedRuc[$ruc] = true;

            $r = mt_rand(1, 100);
            $status = $r <= 84 ? Associate::STATUS_ACTIVO : ($r <= 94 ? Associate::STATUS_SUSPENDIDO : Associate::STATUS_DESAFILIADO);
            $category = $this->weighted(['A' => 8, 'B' => 17, 'C' => 25, 'D' => 30, 'E' => 20]);
            $joined = $today->subDays(mt_rand(30, 365 * 22));
            $anniversary = $joined->subYears(mt_rand(0, 15))->subDays(mt_rand(0, 364));
            $district = $this->pick(self::DISTRICTS);
            $legalRep = $this->person();
            $cchRep = mt_rand(1, 100) <= 55 ? $legalRep : $this->person();
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));

            $rows[] = [
                'name' => $legal,
                'status' => $status,
                'sectorista' => $this->pick(self::SECTORISTAS),
                'category' => $category,
                'monthly_fee' => self::CATEGORY_FEES[$category],
                'joined_at' => $joined->toDateString(),
                'person_type' => mt_rand(1, 100) <= 88 ? 'PERSONA JURÍDICA' : 'PERSONA NATURAL',
                'anniversary_date' => $anniversary->toDateString(),
                'ruc' => $ruc,
                'company' => mt_rand(1, 100) <= 60 ? $name : $legal,
                'contact_phone' => '064'.mt_rand(200000, 299999),
                'email' => $slug.$i.'@'.self::DEMO_DOMAIN,
                'billing_address' => $this->pick(self::STREETS).' '.mt_rand(100, 1999),
                'billing_district' => $district,
                'mailing_address' => mt_rand(1, 100) <= 70 ? null : $this->pick(self::STREETS).' '.mt_rand(100, 1999),
                'mailing_district' => mt_rand(1, 100) <= 70 ? $district : $this->pick(self::DISTRICTS),
                'company_size' => $this->weighted(['MICROEMPRESAS' => 55, 'PEQUEÑA EMPRESA' => 30, 'MEDIANA EMPRESA' => 12, 'GRAN EMPRESA' => 3]),
                'activity_type' => $this->weighted(['COMERCIO' => 45, 'SERVICIO' => 40, 'INDUSTRIALES' => 15]),
                'sector_committee' => $this->pick(self::COMMITTEES),
                'ciiu' => $this->pick(self::CIIU),
                'sub_sector' => $this->pick(self::CIIU),
                'legal_rep_name' => $legalRep['name'],
                'legal_rep_dni' => $legalRep['dni'],
                'legal_rep_gender' => $legalRep['gender'],
                'legal_rep_birthday' => $legalRep['birthday'],
                'legal_rep_phone' => $legalRep['phone'],
                'legal_rep_email' => $legalRep['email'],
                'cch_rep_name' => $cchRep['name'],
                'cch_rep_dni' => $cchRep['dni'],
                'cch_rep_gender' => $cchRep['gender'],
                'cch_rep_birthday' => $cchRep['birthday'],
                'cch_rep_phone' => $cchRep['phone'],
                'cch_rep_email' => $cchRep['email'],
                'image_path' => null,
                'notes' => mt_rand(1, 100) <= 30 ? $this->pick(self::NOTES) : null,
                'is_active' => $status === Associate::STATUS_ACTIVO,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Guarantee birthdays/anniversaries around today so the calendar
        // and the daily notifications have something to show.
        foreach ([0, 0, 0, 1, 1, 2, 3, 5, 7, 10, 14, 21, 28] as $k => $offset) {
            $date = $today->addDays($offset);
            $idx = $k * 7 % count($rows);
            $field = $k % 3 === 2 ? 'anniversary_date' : ($k % 2 ? 'cch_rep_birthday' : 'legal_rep_birthday');
            $year = $field === 'anniversary_date' ? mt_rand(1985, 2020) : mt_rand(1955, 1998);
            $rows[$idx][$field] = sprintf('%04d-%02d-%02d', $year, $date->month, min($date->day, 28));
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('associates')->insert($chunk);
        }

        return Associate::where('email', 'like', '%@'.self::DEMO_DOMAIN)
            ->get(['id', 'monthly_fee', 'joined_at', 'status'])
            ->map(fn (Associate $a) => ['id' => $a->id, 'fee' => (float) $a->monthly_fee, 'joined' => CarbonImmutable::instance($a->joined_at), 'status' => $a->status])
            ->all();
    }

    /**
     * Monthly invoices from FIRST_PERIOD (or the month the associate
     * joined) up to the current period, with payments that leave a
     * realistic mix of paid / partial / pending / overdue cuotas and
     * invoices.paid_total consistent with the active payments.
     *
     * @param  array<int, array{id: int, fee: float, joined: CarbonImmutable, status: string}>  $associates
     * @param  int[]  $users
     * @return array{0: int, 1: int, 2: int}
     */
    private function seedBilling(array $associates, array $users, CarbonImmutable $today): array
    {
        $firstPeriod = CarbonImmutable::createFromFormat('Y-m-d', self::FIRST_PERIOD.'-01')->startOfMonth();
        $currentPeriod = $today->startOfMonth();
        $now = now();
        $receipt = (int) (Invoice::max('id') ?? 0) + 1000;

        $invoiceRows = [];
        $paymentPlans = []; // keyed by "associate:period" → list of payments
        foreach ($associates as $a) {
            $start = $a['joined']->startOfMonth()->greaterThan($firstPeriod) ? $a['joined']->startOfMonth() : $firstPeriod;
            // Unaffiliated/suspended ones stopped being invoiced a while ago
            $end = match ($a['status']) {
                Associate::STATUS_DESAFILIADO => $currentPeriod->subMonths(mt_rand(6, 18)),
                Associate::STATUS_SUSPENDIDO => $currentPeriod->subMonths(mt_rand(1, 4)),
                default => $currentPeriod,
            };
            if ($end->lessThan($start)) {
                continue;
            }
            // A few associates have never had their fee registered → the
            // batch amount applies (S/ 100), like generateForPeriod() does.
            $amount = $a['fee'] > 0 ? $a['fee'] : 100.00;

            for ($p = $start; $p->lessThanOrEqualTo($end); $p = $p->addMonth()) {
                $period = $p->format('Y-m');
                $issue = $p->day(1);
                $due = $p->endOfMonth()->startOfDay();
                $monthsAgo = (int) round($p->diffInMonths($currentPeriod));

                // Payment behaviour: older cuotas are almost always settled;
                // the current one is still being collected.
                $roll = mt_rand(1, 100);
                if ($monthsAgo === 0) {
                    $kind = $roll <= 45 ? 'full' : ($roll <= 55 ? 'partial' : 'none');
                } elseif ($monthsAgo <= 2) {
                    $kind = $roll <= 78 ? 'full' : ($roll <= 88 ? 'partial' : 'none');
                } else {
                    $kind = $roll <= 93 ? 'full' : ($roll <= 96 ? 'partial' : 'none');
                }
                if ($a['status'] === Associate::STATUS_DESAFILIADO && $monthsAgo <= 24 && $roll > 60) {
                    $kind = 'none'; // they left owing the last cuotas
                }

                $payments = [];
                if ($kind === 'full') {
                    if (mt_rand(1, 100) <= 22) {
                        $first = round($amount * mt_rand(30, 70) / 100, 2);
                        $payments[] = ['amount' => $first, 'paid_at' => $this->payDate($issue, $due, $today)];
                        $payments[] = ['amount' => round($amount - $first, 2), 'paid_at' => $this->payDate($issue->addDays(10), $due->addDays(25), $today)];
                    } else {
                        $payments[] = ['amount' => $amount, 'paid_at' => $this->payDate($issue, $due->addDays(15), $today)];
                    }
                } elseif ($kind === 'partial') {
                    $payments[] = ['amount' => round($amount * mt_rand(20, 60) / 100, 2), 'paid_at' => $this->payDate($issue, $due->addDays(10), $today)];
                }

                $paidTotal = round(array_sum(array_column($payments, 'amount')), 2);
                $status = $paidTotal >= $amount ? Invoice::STATUS_PAGADA : ($paidTotal > 0 ? Invoice::STATUS_PARCIAL : Invoice::STATUS_PENDIENTE);

                $invoiceRows[] = [
                    'associate_id' => $a['id'],
                    'period' => $period,
                    'receipt_number' => sprintf('FE01-%06d', $receipt++),
                    'amount' => $amount,
                    'paid_total' => $paidTotal,
                    'issue_date' => $issue->toDateString(),
                    'due_date' => $due->toDateString(),
                    'status' => $status,
                    'created_by' => $users[0],
                    'created_at' => $issue->setTime(8, 0),
                    'updated_at' => $now,
                ];
                if ($payments !== []) {
                    $paymentPlans[$a['id'].':'.$period] = $payments;
                }
            }
        }

        foreach (array_chunk($invoiceRows, 500) as $chunk) {
            DB::table('invoices')->insert($chunk);
        }

        $invoiceIds = Invoice::query()
            ->whereIn('associate_id', array_column($associates, 'id'))
            ->get(['id', 'associate_id', 'period'])
            ->mapWithKeys(fn (Invoice $i) => [$i->associate_id.':'.$i->period => $i->id]);

        $paymentRows = [];
        $voids = 0;
        foreach ($paymentPlans as $key => $payments) {
            $invoiceId = $invoiceIds[$key];
            foreach ($payments as $payment) {
                $registeredBy = $this->pick($users);
                $paidAt = $payment['paid_at'];
                // ~1.5% of payments were registered wrong, voided and
                // re-entered: both rows are kept (the void is excluded
                // from paid_total, which already only counts the good one).
                if (mt_rand(1, 1000) <= 15) {
                    $paymentRows[] = $this->paymentRow($invoiceId, $payment['amount'], $paidAt, $registeredBy, $now, [
                        'voided_at' => $paidAt->addDays(mt_rand(0, 3))->setTime(mt_rand(9, 17), mt_rand(0, 59)),
                        'voided_by' => $this->pick($users),
                        'void_reason' => $this->pick(['Monto digitado incorrectamente', 'Se registró en la factura equivocada', 'Pago duplicado', 'Voucher no correspondía a este asociado']),
                    ]);
                    $voids++;
                }
                $paymentRows[] = $this->paymentRow($invoiceId, $payment['amount'], $paidAt, $registeredBy, $now);
            }
        }

        foreach (array_chunk($paymentRows, 500) as $chunk) {
            DB::table('payments')->insert($chunk);
        }

        return [count($invoiceRows), count($paymentRows), $voids];
    }

    /** @param  array<string, mixed>  $void */
    private function paymentRow(int $invoiceId, float $amount, CarbonImmutable $paidAt, int $registeredBy, $now, array $void = []): array
    {
        return [
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'paid_at' => $paidAt->setTime(mt_rand(8, 18), mt_rand(0, 59)),
            'method' => $this->weighted(self::METHOD_WEIGHTS),
            'registered_by' => $registeredBy,
            'notes' => mt_rand(1, 100) <= 25 ? $this->pick(self::PAYMENT_NOTES) : null,
            'voided_at' => $void['voided_at'] ?? null,
            'voided_by' => $void['voided_by'] ?? null,
            'void_reason' => $void['void_reason'] ?? null,
            'created_at' => $paidAt,
            'updated_at' => $now,
        ];
    }

    /** A payment date between $from and $to, never in the future. */
    private function payDate(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $today): CarbonImmutable
    {
        $to = $to->greaterThan($today) ? $today : $to;
        if ($to->lessThanOrEqualTo($from)) {
            return $from->greaterThan($today) ? $today : $from;
        }

        return $from->addDays(mt_rand(0, (int) $from->diffInDays($to)));
    }

    /** @return array{name: string, dni: string, gender: string, birthday: string, phone: string, email: string} */
    private function person(): array
    {
        $gender = mt_rand(0, 1) ? 'MASCULINO' : 'FEMENINO';
        $first = $this->pick($gender === 'MASCULINO' ? self::FIRST_NAMES_M : self::FIRST_NAMES_F);
        $second = mt_rand(1, 100) <= 60 ? ' '.$this->pick($gender === 'MASCULINO' ? self::FIRST_NAMES_M : self::FIRST_NAMES_F) : '';
        $name = $this->pick(self::SURNAMES).' '.$this->pick(self::SURNAMES).' '.$first.$second;
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '.', iconv('UTF-8', 'ASCII//TRANSLIT', $first.' '.explode(' ', $name)[0])));

        return [
            'name' => $name,
            'dni' => (string) mt_rand(10000000, 79999999),
            'gender' => $gender,
            'birthday' => sprintf('%04d-%02d-%02d', mt_rand(1950, 1999), mt_rand(1, 12), mt_rand(1, 28)),
            'phone' => '9'.mt_rand(10000000, 99999999),
            'email' => mt_rand(1, 100) <= 75 ? $slug.mt_rand(1, 99).'@gmail.com' : null,
        ];
    }

    /** @param  array<int|string, mixed>  $items */
    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    /** @param  array<string, int>  $weights */
    private function weighted(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_first($weights);
    }
}
