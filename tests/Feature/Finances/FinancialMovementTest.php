<?php

namespace Tests\Feature\Finances;

use App\Enums\MovementDirection;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\Payment;
use App\Services\Finances\FinancialMovementService;
use App\Support\CurrentOrganization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinancialMovementTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(FinancialCategory $category, Branch $branch, array $overrides = []): array
    {
        return [
            'direction' => $category->direction->value,
            'category_id' => $category->id,
            'concept' => '  Compra   de resinas ',
            'occurred_on' => today()->toDateString(),
            'amount' => '$1,250.50',
            'method' => PaymentMethod::Transfer->value,
            'branch_id' => $branch->id,
            ...$overrides,
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/finanzas/movimientos')->assertRedirect('/login');
    }

    public function test_finances_entry_shows_the_summary(): void
    {
        $this->actingAsMember($this->member())->get('/finanzas')->assertOk()->assertViewIs('finances.index');
    }

    public function test_registers_income_and_expense_with_audit(): void
    {
        $member = $this->member();
        $branch = $this->branch($member, ['is_main' => true]);
        $income = $this->category($member);
        $expense = $this->category($member, MovementDirection::Expense);

        $this->actingAsMember($member)
            ->post('/finanzas/movimientos', $this->payload($income, $branch))
            ->assertRedirect(route('finances.movements.index'))
            ->assertSessionHas('toast.title', 'Movimiento registrado correctamente');
        $this->actingAsMember($member)
            ->post('/finanzas/movimientos', $this->payload($expense, $branch, ['amount' => '300']))
            ->assertSessionHasNoErrors();

        $saved = FinancialMovement::where('direction', MovementDirection::Income->value)->sole();
        $this->assertSame('Compra de resinas', $saved->concept);
        $this->assertSame('1250.50', $saved->amount);
        $this->assertSame($member->user_id, $saved->created_by);
        $this->assertSame($member->organization_id, $saved->organization_id);
        $this->assertNull($saved->cash_session_id);
        $this->assertSame('300.00', FinancialMovement::where('direction', MovementDirection::Expense->value)->sole()->amount);
        $this->assertSame(2, AuditLog::where('entity_type', 'financial_movements')->where('action', 'insert')->count());
    }

    public function test_validates_amount_date_concept_and_category(): void
    {
        $member = $this->member();
        $branch = $this->branch($member);
        $income = $this->category($member);
        $expense = $this->category($member, MovementDirection::Expense);
        $inactive = $this->category($member, MovementDirection::Income, ['status' => 'inactive']);

        $cases = [
            [['amount' => '0'], 'amount', 'El monto debe ser mayor que 0.'],
            [['amount' => '-50'], 'amount', 'El monto debe ser mayor que 0.'],
            [['amount' => '10.555'], 'amount', 'El monto admite máximo dos decimales.'],
            [['amount' => '1000000000000'], 'amount', 'El monto no puede ser mayor que 999,999,999,999.99.'],
            [['amount' => 'abc'], 'amount', 'El monto debe ser un número.'],
            [['occurred_on' => today()->addDay()->toDateString()], 'occurred_on', 'La fecha no puede ser posterior a hoy.'],
            [['occurred_on' => today()->subYear()->subDay()->toDateString()], 'occurred_on', 'La fecha no puede tener más de un año de antigüedad.'],
            [['category_id' => $expense->id], 'category_id', 'La categoría no corresponde al tipo de movimiento.'],
            [['category_id' => $inactive->id], 'category_id', 'La categoría seleccionada está inactiva.'],
            [['concept' => 'ab'], 'concept', null],
            [['method' => 'cheque'], 'method', null],
        ];

        foreach ($cases as [$overrides, $field, $message]) {
            $this->actingAsMember($member)
                ->post('/finanzas/movimientos', $this->payload($income, $branch, $overrides))
                ->assertSessionHasErrors($message ? [$field => $message] : [$field], null, 'createMovement');
        }

        $this->assertSame(0, FinancialMovement::count());
    }

    public function test_cash_requires_an_open_session_of_the_same_branch(): void
    {
        $member = $this->member();
        $branch = $this->branch($member, ['is_main' => true]);
        $otherBranch = $this->branch($member);
        $income = $this->category($member);
        $cash = ['method' => PaymentMethod::Cash->value];

        $this->actingAsMember($member)
            ->post('/finanzas/movimientos', $this->payload($income, $branch, $cash))
            ->assertSessionHasErrors(['cash_session_id' => 'Abre una caja en esta sucursal para registrar movimientos en efectivo'], null, 'createMovement');

        $otherSession = $this->openSession($member, $otherBranch);
        $this->actingAsMember($member)
            ->post('/finanzas/movimientos', $this->payload($income, $branch, [...$cash, 'cash_session_id' => $otherSession->id]))
            ->assertSessionHasErrors(['cash_session_id' => 'Abre una caja en esta sucursal para registrar movimientos en efectivo'], null, 'createMovement');

        $session = $this->openSession($member, $branch);
        $this->actingAsMember($member)
            ->post('/finanzas/movimientos', $this->payload($income, $branch, [...$cash, 'cash_session_id' => $otherSession->id]))
            ->assertSessionHasErrors(['cash_session_id' => 'La caja seleccionada no está abierta en esta sucursal.'], null, 'createMovement');
        $this->assertSame(0, FinancialMovement::count());

        $this->actingAsMember($member)
            ->post('/finanzas/movimientos', $this->payload($income, $branch, [...$cash, 'cash_session_id' => $session->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($session->id, FinancialMovement::sole()->cash_session_id);

        // Otros métodos nunca quedan ligados a la caja.
        $this->actingAsMember($member)
            ->post('/finanzas/movimientos', $this->payload($income, $branch, ['cash_session_id' => $session->id, 'concept' => 'Transferencia']))
            ->assertSessionHasNoErrors();
        $this->assertNull(FinancialMovement::where('concept', 'Transferencia')->sole()->cash_session_id);
    }

    public function test_receipt_is_stored_privately_and_downloaded(): void
    {
        Storage::fake('local');
        $member = $this->member();
        $branch = $this->branch($member);
        $income = $this->category($member);

        $this->actingAsMember($member)
            ->post('/finanzas/movimientos', $this->payload($income, $branch, ['receipt' => UploadedFile::fake()->create('ticket.pdf', 120, 'application/pdf')]))
            ->assertSessionHasNoErrors();

        $movement = FinancialMovement::sole();
        $this->assertSame("org/{$member->organization_id}/finanzas/comprobantes/{$movement->id}.pdf", $movement->receipt_storage_key);
        Storage::disk('local')->assertExists($movement->receipt_storage_key);

        $this->actingAsMember($member)
            ->get(route('finances.movements.receipt', $movement))
            ->assertOk()
            ->assertDownload('comprobante-'.today()->toDateString().'.pdf');

        $this->actingAsMember($member)
            ->post('/finanzas/movimientos', $this->payload($income, $branch, ['receipt' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]))
            ->assertSessionHasErrors(['receipt' => 'El comprobante debe ser una imagen JPG o PNG, o un PDF.'], null, 'createMovement');
        $this->actingAsMember($member)
            ->post('/finanzas/movimientos', $this->payload($income, $branch, ['receipt' => UploadedFile::fake()->create('grande.pdf', 6000, 'application/pdf')]))
            ->assertSessionHasErrors(['receipt' => 'El comprobante no debe pesar más de 5 MB.'], null, 'createMovement');

        $this->actingAsMember($this->member())->get(route('finances.movements.receipt', $movement))->assertNotFound();
    }

    public function test_reversal_creates_mirror_movement_and_totals_exclude_the_pair(): void
    {
        $member = $this->member();
        $branch = $this->branch($member);
        $income = $this->category($member);
        $expense = $this->category($member, MovementDirection::Expense);
        $original = $this->movement($member, $income, $branch, ['amount' => '1000.00', 'concept' => 'Cobro duplicado']);
        $rent = $this->movement($member, $expense, $branch, ['amount' => '300.00', 'concept' => 'Renta']);
        $cleaning = $this->movement($member, $income, $branch, ['amount' => '200.00', 'concept' => 'Limpieza']);

        $this->actingAsMember($member)
            ->post(route('finances.movements.reverse', $original), ['reason' => '  Monto   equivocado '])
            ->assertSessionHas('toast.title', 'Movimiento revertido');

        $reversal = FinancialMovement::where('reversed_of_id', $original->id)->sole();
        $this->assertSame('Reversión: Monto equivocado', $reversal->concept);
        $this->assertSame('1000.00', $reversal->amount);
        $this->assertSame(MovementDirection::Income, $reversal->direction);
        $this->assertSame([$income->id, $branch->id, PaymentMethod::Transfer], [$reversal->category_id, $reversal->branch_id, $reversal->method]);
        $this->assertSame($member->user_id, $reversal->created_by);
        $this->assertTrue(AuditLog::where('entity_id', $original->id)->where('action', 'reverse')->exists());

        $this->actingAsMember($member)->get('/finanzas/movimientos')
            ->assertOk()
            ->assertViewHas('totals', ['income' => '200.00', 'expense' => '300.00', 'net' => '-100.00'])
            ->assertViewHas('movements', fn ($page) => $page->pluck('id')->sort()->values()->all() === collect([$rent->id, $cleaning->id])->sort()->values()->all());

        $this->actingAsMember($member)->get('/finanzas/movimientos?revertidos=1')
            ->assertViewHas('movements', fn ($page) => $page->total() === 4)
            ->assertViewHas('totals', ['income' => '200.00', 'expense' => '300.00', 'net' => '-100.00'])
            ->assertSee('Revertido')
            ->assertSee('Reversión');
    }

    public function test_cannot_reverse_twice_or_reverse_a_reversal(): void
    {
        $member = $this->member();
        $original = $this->movement($member, $this->category($member), $this->branch($member));

        $this->actingAsMember($member)
            ->post(route('finances.movements.reverse', $original), ['reason' => ''])
            ->assertSessionHasErrors(['reason'], null, 'reverseMovement');

        $this->actingAsMember($member)->post(route('finances.movements.reverse', $original), ['reason' => 'Error'])->assertSessionHasNoErrors();
        $reversal = FinancialMovement::where('reversed_of_id', $original->id)->sole();

        $this->actingAsMember($member)
            ->post(route('finances.movements.reverse', $original), ['reason' => 'Otra vez'])
            ->assertSessionHas('toast', ['type' => 'error', 'title' => 'Este movimiento ya fue revertido.']);
        $this->actingAsMember($member)
            ->post(route('finances.movements.reverse', $reversal), ['reason' => 'Deshacer'])
            ->assertSessionHas('toast', ['type' => 'error', 'title' => 'Una reversión no se puede revertir.']);

        $this->assertSame(2, FinancialMovement::count());
    }

    public function test_cannot_reverse_a_movement_that_comes_from_a_payment(): void
    {
        $member = $this->member();
        $branch = $this->branch($member);
        $movement = app(FinancialMovementService::class)->recordFromPayment($this->payment($member, $branch));

        $this->actingAsMember($member)
            ->post(route('finances.movements.reverse', $movement), ['reason' => 'Error'])
            ->assertSessionHas('toast', ['type' => 'error', 'title' => 'Este ingreso proviene de un pago; reviértelo desde Pagos']);
        $this->assertSame(1, FinancialMovement::count());
    }

    public function test_cash_reversal_uses_the_open_session_and_is_blocked_after_the_cut(): void
    {
        $member = $this->member();
        $branch = $this->branch($member);
        $income = $this->category($member);
        $session = $this->openSession($member, $branch);
        $cash = ['method' => PaymentMethod::Cash, 'cash_session_id' => $session->id];
        $first = $this->movement($member, $income, $branch, $cash);
        $second = $this->movement($member, $income, $branch, $cash);

        $this->actingAsMember($member)->post(route('finances.movements.reverse', $first), ['reason' => 'Error'])->assertSessionHasNoErrors();
        $this->assertSame($session->id, FinancialMovement::where('reversed_of_id', $first->id)->sole()->cash_session_id);

        $this->closeSession($session, $member);
        $this->actingAsMember($member)
            ->post(route('finances.movements.reverse', $second), ['reason' => 'Error'])
            ->assertSessionHas('toast', ['type' => 'error', 'title' => 'La caja ya fue cortada; registra un ajuste de caja']);
        $this->assertFalse(FinancialMovement::where('reversed_of_id', $second->id)->exists());
    }

    public function test_record_from_payment_is_idempotent(): void
    {
        $member = $this->member();
        $branch = $this->branch($member);
        $payment = $this->payment($member, $branch);
        $service = app(FinancialMovementService::class);

        $first = $service->recordFromPayment($payment);
        $second = $service->recordFromPayment($payment);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, FinancialMovement::count());
        $this->assertSame(FinancialMovementService::PATIENT_PAYMENTS_CATEGORY, $first->category->name);
        $this->assertSame(MovementDirection::Income, $first->direction);
        $this->assertSame("Pago de {$payment->patient->full_name} ({$payment->patient->folio})", $first->concept);
        $this->assertSame(['850.00', $payment->paid_at->toDateString(), $payment->id, $branch->id], [$first->amount, $first->occurred_on->toDateString(), $first->payment_id, $first->branch_id]);
    }

    public function test_movements_of_another_organization_are_not_accessible(): void
    {
        $other = $this->member();
        $foreign = $this->movement($other, $this->category($other), $this->branch($other), ['concept' => 'MOVIMIENTO AJENO']);

        $member = $this->member();
        $this->actingAsMember($member)->get('/finanzas/movimientos')->assertOk()->assertDontSee('MOVIMIENTO AJENO');
        $this->actingAsMember($member)->post(route('finances.movements.reverse', $foreign), ['reason' => 'x'])->assertNotFound();
        $this->assertFalse(FinancialMovement::withoutGlobalScopes()->where('reversed_of_id', $foreign->id)->exists());
    }

    public function test_filters_and_csv_export(): void
    {
        $member = $this->member();
        $branch = $this->branch($member);
        $this->movement($member, $this->category($member), $branch, ['concept' => 'Cobro de consulta', 'occurred_on' => today()->subDays(10)->toDateString()]);
        $this->movement($member, $this->category($member, MovementDirection::Expense), $branch, ['concept' => 'Renta del local', 'method' => PaymentMethod::Card]);
        $this->movement($member, $this->category($member, MovementDirection::Expense), $branch, ['concept' => '=HYPERLINK("http://x")']);

        $this->actingAsMember($member)->get('/finanzas/movimientos?tipo=egreso')->assertSee('Renta del local')->assertDontSee('Cobro de consulta');
        $this->actingAsMember($member)->get('/finanzas/movimientos?q=consulta')->assertSee('Cobro de consulta')->assertDontSee('Renta del local');
        $this->actingAsMember($member)->get('/finanzas/movimientos?metodo=tarjeta')->assertSee('Renta del local')->assertDontSee('Cobro de consulta');
        $this->actingAsMember($member)->get('/finanzas/movimientos?desde='.today()->subDays(2)->toDateString())->assertSee('Renta del local')->assertDontSee('Cobro de consulta');

        $response = $this->actingAsMember($member)->get('/finanzas/movimientos/exportar?tipo=egreso')->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Renta del local', $csv);
        $this->assertStringNotContainsString('Cobro de consulta', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_trigger_errors_are_translated_to_spanish(): void
    {
        $member = $this->member();
        $session = $this->openSession($member, $this->branch($member));
        $this->closeSession($session, $member);

        try {
            $session->update(['notes' => 'Cambio']);
            $this->fail('El trigger debió bloquear la actualización.');
        } catch (QueryException $e) {
            $this->assertSame('La caja ya fue cortada; registra un ajuste de caja.', BusinessRuleException::fromQueryException($e)?->getMessage());
        }
    }

    private function payment($member, Branch $branch): Payment
    {
        $patient = $this->createPatient($member);
        app(CurrentOrganization::class)->set($member);

        return Payment::create([
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'paid_at' => now()->subDays(3),
            'amount' => '850.00',
            'method' => PaymentMethod::Card,
            'idempotency_key' => 'pago-'.$patient->id,
            'created_by' => $member->user_id,
        ]);
    }
}
