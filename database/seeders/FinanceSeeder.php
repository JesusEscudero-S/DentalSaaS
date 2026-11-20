<?php

namespace Database\Seeders;

use App\Enums\CashSessionStatus;
use App\Enums\MovementDirection;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CashSessionAdjustment;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finances\FinancialMovementService;
use App\Support\CurrentOrganization;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Finanzas de la clínica demo: sucursal principal, categorías, "Caja recepción" con una sesión abierta
 * hoy, dos cortes anteriores (uno con faltante y ajuste) y ~60 movimientos de los últimos 6 meses
 * (incluye pagos de pacientes y una reversión). Es idempotente: cada bloque se crea solo si no existe.
 */
class FinanceSeeder extends Seeder
{
    public const MAIN_BRANCH = 'Matriz';

    public const REGISTER = 'Caja recepción';

    private const CATEGORIES = [
        'ingreso' => [FinancialMovementService::PATIENT_PAYMENTS_CATEGORY, 'Otros ingresos'],
        'egreso' => [
            'Renta', 'Nómina', 'Material dental', 'Laboratorio dental', 'Servicios (luz, agua, internet)',
            'Mantenimiento', 'Publicidad', 'Otros egresos',
        ],
    ];

    /** Egresos de cada mes: [categoría, concepto, día, monto base, variación, método] */
    private const MONTHLY = [
        ['Renta', 'Renta del local', 1, '14000.00', 0, 'transferencia'],
        ['Servicios (luz, agua, internet)', 'Luz, agua e internet', 5, '2850.00', 400, 'domiciliado'],
        ['Material dental', 'Compra de material dental', 8, '5600.00', 2400, 'tarjeta'],
        ['Nómina', 'Nómina mensual', 15, '28000.00', 0, 'transferencia'],
        ['Laboratorio dental', 'Trabajos de laboratorio', 20, '3900.00', 2800, 'transferencia'],
    ];

    /** Pagos de pacientes de cada mes: día => método */
    private const MONTHLY_PAYMENTS = [6 => 'tarjeta', 14 => 'transferencia', 25 => 'tarjeta'];

    /** Movimientos únicos: [meses atrás, día, categoría, concepto, monto, método] */
    private const EXTRAS = [
        [4, 12, 'Publicidad', 'Campaña en redes sociales', '2500.00', 'tarjeta'],
        [3, 18, 'Mantenimiento', 'Mantenimiento del compresor', '1800.00', 'transferencia'],
        [2, 22, 'Otros ingresos', 'Venta de cepillos y enjuagues', '1250.00', 'transferencia'],
        [1, 12, 'Publicidad', 'Volanteo en la colonia', '1600.00', 'tarjeta'],
    ];

    private const PAYMENT_AMOUNTS = ['21500.00', '26000.00', '14800.00', '33500.00', '11600.00', '29000.00', '17400.00'];

    /**
     * Cortes de caja ya cerrados: [días atrás, apertura, contado, notas, efectivo, ajuste].
     * Efectivo: ['pago', monto] o ['egreso', monto, concepto]. Ajuste: [monto, motivo] o null.
     */
    private const CLOSED_SESSIONS = [
        [8, '1000.00', '3210.00', null, [['pago', '1850.00'], ['pago', '600.00'], ['egreso', '240.00', 'Café y galletas para la sala de espera']], null],
        [4, '1000.00', '4050.00', 'Faltaron $50 al contar; se revisará con recepción.', [['pago', '2300.00'], ['pago', '950.00'], ['egreso', '150.00', 'Mensajería']], ['50.00', 'Se encontró un billete de $50 en el cajón de recetas.']],
    ];

    private Branch $branch;

    /** @var Collection<string, string> nombre => id */
    private Collection $categories;

    private User $admin;

    private User $receptionist;

    private Collection $patients;

    private FinancialMovementService $movements;

    private int $paymentIndex = 0;

    public function run(FinancialMovementService $movements, CurrentOrganization $current): void
    {
        $organization = Organization::where('commercial_name', DemoOrganizationSeeder::ORGANIZATION)->firstOrFail();

        // Los pagos de demo necesitan pacientes (PatientSeeder es idempotente).
        $this->callOnce(PatientSeeder::class);

        $admin = OrganizationMember::where('organization_id', $organization->id)
            ->whereHas('role', fn ($q) => $q->where('code', 'admin'))
            ->with('user')
            ->firstOrFail();
        $current->set($admin);

        DB::transaction(function () use ($organization, $admin, $movements) {
            $this->branch = $this->mainBranch($organization);

            foreach (self::CATEGORIES as $direction => $names) {
                foreach ($names as $name) {
                    FinancialCategory::firstOrCreate(['name' => $name, 'direction' => $direction]);
                }
            }

            $register = CashRegister::firstOrCreate(['branch_id' => $this->branch->id, 'name' => self::REGISTER]);

            $this->categories = FinancialCategory::pluck('id', 'name');
            $this->admin = $admin->user;
            $this->receptionist = User::where('email', 'karla.mendez@demo.test')->first() ?? $admin->user;
            $this->patients = Patient::where('status', 'active')->orderBy('folio')->get();
            $this->movements = $movements;

            if (FinancialMovement::doesntExist()) {
                $this->seedMovements($register);
            }

            if (CashSession::where('status', CashSessionStatus::Closed->value)->doesntExist()) {
                $this->seedClosedSessions($register);
            }
        });

        $current->clear();
    }

    /**
     * Solo puede haber una sucursal principal activa (uq_one_main_branch): si otro seeder ya creó una,
     * se reutiliza; si no, se crea "Matriz".
     */
    private function mainBranch(Organization $organization): Branch
    {
        return Branch::where('organization_id', $organization->id)
            ->where('is_main', true)
            ->where('status', 'active')
            ->first()
            ?? Branch::firstOrCreate(
                ['organization_id' => $organization->id, 'name' => self::MAIN_BRANCH],
                ['is_main' => true],
            );
    }

    private function seedMovements(CashRegister $register): void
    {
        $today = today();
        $add = $this->addMovement(...);
        $pay = $this->addPayment(...);

        for ($monthsAgo = 5; $monthsAgo >= 0; $monthsAgo--) {
            $month = $today->copy()->startOfMonth()->subMonthsNoOverflow($monthsAgo);
            $day = fn (int $d) => $month->copy()->addDays($d - 1);

            foreach (self::MONTHLY as [$category, $concept, $d, $base, $spread, $method]) {
                if ($day($d)->lte($today)) {
                    $variation = $spread ? (string) (($monthsAgo * 739) % $spread) : '0';
                    $add($day($d), $category, $concept, bcadd($base, $variation, 2), $method);
                }
            }

            foreach (self::MONTHLY_PAYMENTS as $d => $method) {
                if ($day($d)->lte($today)) {
                    $pay($day($d), $method);
                }
            }

            foreach (self::EXTRAS as [$extraMonthsAgo, $d, $category, $concept, $amount, $method]) {
                if ($extraMonthsAgo === $monthsAgo && $day($d)->lte($today)) {
                    $add($day($d), $category, $concept, $amount, $method);
                }
            }
        }

        // Una captura duplicada que se corrigió con una reversión (RF-03).
        $duplicate = $add($today->copy()->subDays(3), 'Otros egresos', 'Papelería y artículos de oficina', '450.00', PaymentMethod::Card->value);
        $add($today->copy()->subDays(2), 'Otros egresos', 'Reversión: se capturó dos veces', '450.00', PaymentMethod::Card->value, ['reversed_of_id' => $duplicate->id]);

        // Caja abierta hoy con movimientos en efectivo (RF-02).
        $session = CashSession::create([
            'cash_register_id' => $register->id,
            'opened_by' => $this->receptionist->id,
            'opened_at' => $today->copy()->setTime(9, 0),
            'opening_amount' => '1500.00',
        ]);
        $pay($today, PaymentMethod::Cash->value, $session->id, '950.00');
        $add($today, 'Otros egresos', 'Garrafones de agua', '180.00', PaymentMethod::Cash->value, ['cash_session_id' => $session->id]);
    }

    /** RF-08 / RF-09: un corte cuadrado y otro con faltante que se corrigió con un ajuste. */
    private function seedClosedSessions(CashRegister $register): void
    {
        foreach (self::CLOSED_SESSIONS as [$daysAgo, $opening, $counted, $notes, $cash, $adjustment]) {
            $day = today()->subDays($daysAgo);
            $expected = $opening;
            foreach ($cash as [$type, $amount]) {
                $expected = $type === 'pago' ? bcadd($expected, $amount, 2) : bcsub($expected, $amount, 2);
            }

            // Se inserta ya cerrada: la caja tiene otra sesión abierta (uq_cash_one_open) y, una vez
            // cerrada, el trigger impide actualizarla.
            $session = CashSession::create([
                'cash_register_id' => $register->id,
                'opened_by' => $this->receptionist->id,
                'opened_at' => $day->copy()->setTime(9, 0),
                'opening_amount' => $opening,
                'status' => CashSessionStatus::Closed,
                'closed_by' => $this->admin->id,
                'closed_at' => $day->copy()->setTime(19, 0),
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'difference' => bcsub($counted, $expected, 2),
                'notes' => $notes,
            ]);

            foreach ($cash as $line) {
                if ($line[0] === 'pago') {
                    $this->addPayment($day, PaymentMethod::Cash->value, $session->id, $line[1]);
                } else {
                    $this->addMovement($day, 'Otros egresos', $line[2], $line[1], PaymentMethod::Cash->value, ['cash_session_id' => $session->id]);
                }
            }

            if ($adjustment) {
                (new CashSessionAdjustment)->forceFill([
                    'cash_session_id' => $session->id,
                    'amount' => $adjustment[0],
                    'reason' => $adjustment[1],
                    'created_by' => $this->admin->id,
                    'created_at' => $day->copy()->addDay()->setTime(10, 0),
                ])->save();
            }
        }
    }

    private function addMovement(CarbonInterface $date, string $category, string $concept, string $amount, string $method, array $extra = []): FinancialMovement
    {
        $direction = in_array($category, self::CATEGORIES['ingreso'], true) ? MovementDirection::Income : MovementDirection::Expense;

        return tap((new FinancialMovement)->forceFill([
            'branch_id' => $this->branch->id,
            'direction' => $direction,
            'category_id' => $this->categories[$category],
            'concept' => $concept,
            'occurred_on' => $date->toDateString(),
            'amount' => $amount,
            'method' => $method,
            'created_by' => $this->admin->id,
            'created_at' => $date->copy()->setTime(10, 30),
            ...$extra,
        ]), fn (FinancialMovement $m) => $m->save());
    }

    /** Pago de un paciente y su ingreso, vía FinancialMovementService::recordFromPayment() (RF-10). */
    private function addPayment(CarbonInterface $date, string $method, ?string $sessionId = null, ?string $amount = null): FinancialMovement
    {
        $payment = Payment::create([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patients[$this->paymentIndex % $this->patients->count()]->id,
            'paid_at' => $date->copy()->setTime(12, 0),
            'amount' => $amount ?? self::PAYMENT_AMOUNTS[$this->paymentIndex % count(self::PAYMENT_AMOUNTS)],
            'method' => $method,
            'idempotency_key' => 'seed-'.Str::uuid(),
            'cash_session_id' => $sessionId,
            'created_by' => $this->receptionist->id,
        ]);
        $this->paymentIndex++;

        return $this->movements->recordFromPayment($payment);
    }
}
