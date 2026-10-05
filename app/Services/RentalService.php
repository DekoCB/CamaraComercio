<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\RentalCatering;
use App\Models\RentalLineItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Alquiler de espacios a asociados (nuevo módulo, sept-2026): una
 * cotización y una reserva confirmada son el mismo registro en distintos
 * momentos de su ciclo (ver Rental), así que "crear una cotización" es
 * simplemente crear un Rental en COTIZADA — confirmarla más adelante no
 * crea una fila nueva, la avanza de estado.
 */
class RentalService
{
    /**
     * @param  array{space_id: int, associate_id?: ?int, client_name?: ?string, starts_at: \DateTimeInterface, ends_at: \DateTimeInterface, amount: float, purpose?: ?string, bank_account?: ?string, notes?: ?string, line_items?: array, catering?: ?array}  $data
     */
    public function create(array $data, int $userId): Rental
    {
        return DB::transaction(function () use ($data, $userId) {
            $rental = Rental::create([
                'space_id' => $data['space_id'],
                'associate_id' => $data['associate_id'] ?? null,
                'client_name' => $data['client_name'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'purpose' => $data['purpose'] ?? null,
                'amount' => $data['amount'],
                'bank_account' => $data['bank_account'] ?? null,
                'status' => Rental::STATUS_COTIZADA,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->syncLineItems($rental, $data['line_items'] ?? []);
            $this->syncCatering($rental, $data['catering'] ?? null);

            return $rental;
        });
    }

    /**
     * @param  array{space_id: int, associate_id?: ?int, client_name?: ?string, starts_at: \DateTimeInterface, ends_at: \DateTimeInterface, amount: float, purpose?: ?string, bank_account?: ?string, notes?: ?string, line_items?: array, catering?: ?array}  $data
     */
    public function update(Rental $rental, array $data): Rental
    {
        return DB::transaction(function () use ($rental, $data) {
            $locked = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();

            $this->assertEditable($locked);

            $spaceId = $data['space_id'];
            $startsAt = $data['starts_at'];
            $endsAt = $data['ends_at'];

            if ($locked->status === Rental::STATUS_CONFIRMADA) {
                $this->assertNoConflict($spaceId, $startsAt, $endsAt, $locked->id);
            }

            $locked->update([
                'space_id' => $spaceId,
                'associate_id' => $data['associate_id'] ?? null,
                'client_name' => $data['client_name'] ?? null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'purpose' => $data['purpose'] ?? null,
                'amount' => $data['amount'],
                'bank_account' => $data['bank_account'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncLineItems($locked, $data['line_items'] ?? []);
            $this->syncCatering($locked, $data['catering'] ?? null);

            return $locked;
        });
    }

    /**
     * Reemplaza por completo los ítems del alquiler en cada guardado —
     * mismo criterio que AssociateInscriptionService con ejecutivos y
     * productos: más simple que calcular un diff, y aquí no hay nada que
     * referencie un ítem desde afuera. Filas sin cantidad se descartan.
     */
    private function syncLineItems(Rental $rental, array $items): void
    {
        $rental->lineItems()->delete();

        foreach (array_values($items) as $i => $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            RentalLineItem::create([
                'rental_id' => $rental->id,
                'catalog_item_id' => $item['catalog_item_id'] ?? null,
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'hourly_rate' => $item['hourly_rate'] !== '' && isset($item['hourly_rate']) ? $item['hourly_rate'] : null,
                'sort_order' => $i,
            ]);
        }
    }

    /** Null o sin costo/personas = no se pidió coffee break para este alquiler. */
    private function syncCatering(Rental $rental, ?array $catering): void
    {
        $hasContent = $catering && (
            ! empty($catering['people_count']) || ! empty($catering['daily_cost'])
            || ! empty($catering['drink_option']) || ! empty($catering['sandwich_option']) || ! empty($catering['dessert_option'])
        );

        if (! $hasContent) {
            $rental->catering()->delete();

            return;
        }

        RentalCatering::updateOrCreate(['rental_id' => $rental->id], [
            'people_count' => $catering['people_count'] ?? null,
            'drink_option' => $catering['drink_option'] ?? null,
            'sandwich_option' => $catering['sandwich_option'] ?? null,
            'dessert_option' => $catering['dessert_option'] ?? null,
            'daily_cost' => $catering['daily_cost'] ?? null,
            'notes' => $catering['notes'] ?? null,
        ]);
    }

    /**
     * COTIZADA → CONFIRMADA. This is the point where the space is
     * actually locked for the slot, so it's the only step that checks for
     * a clash against other confirmed/facturada bookings — two open
     * cotizaciones for the same slot are fine (nothing is promised yet).
     */
    public function confirm(Rental $rental): Rental
    {
        return DB::transaction(function () use ($rental) {
            $locked = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Rental::STATUS_COTIZADA) {
                throw new InvalidArgumentException('Solo una cotización puede confirmarse.');
            }

            $this->assertNoConflict($locked->space_id, $locked->starts_at, $locked->ends_at, $locked->id);

            $locked->update(['status' => Rental::STATUS_CONFIRMADA]);

            return $locked;
        });
    }

    /** CONFIRMADA → FACTURADA, una vez cobrado el alquiler. */
    public function bill(Rental $rental): Rental
    {
        return DB::transaction(function () use ($rental) {
            $locked = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Rental::STATUS_CONFIRMADA) {
                throw new InvalidArgumentException('Solo una reserva confirmada puede facturarse.');
            }

            $locked->update(['status' => Rental::STATUS_FACTURADA]);

            return $locked;
        });
    }

    /**
     * Soft cancel, same rule as Invoice/Payment: never a physical delete,
     * and — like an invoice with payments already registered — a
     * facturada rental can no longer be cancelled from here.
     */
    public function cancel(Rental $rental, string $reason, int $cancelledBy): Rental
    {
        return DB::transaction(function () use ($rental, $reason, $cancelledBy) {
            $locked = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw new InvalidArgumentException('Este alquiler ya fue cancelado.');
            }
            if ($locked->status === Rental::STATUS_FACTURADA) {
                throw new InvalidArgumentException('No se puede cancelar un alquiler ya facturado.');
            }

            $locked->update([
                'status' => Rental::STATUS_CANCELADA,
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy,
                'cancel_reason' => $reason,
            ]);

            return $locked;
        });
    }

    private function assertEditable(Rental $rental): void
    {
        if ($rental->isCancelled()) {
            throw new InvalidArgumentException('Este alquiler ya fue cancelado.');
        }
        if ($rental->status === Rental::STATUS_FACTURADA) {
            throw new InvalidArgumentException('No se puede editar un alquiler ya facturado.');
        }
    }

    private function assertNoConflict(int $spaceId, \DateTimeInterface $start, \DateTimeInterface $end, ?int $ignoreId = null): void
    {
        $clash = Rental::query()->blocksSpace()->overlapping($spaceId, $start, $end, $ignoreId)->exists();
        if ($clash) {
            throw new InvalidArgumentException('El espacio ya tiene una reserva confirmada que se cruza con ese horario.');
        }
    }

    /**
     * Every non-cancelled rental whose day falls in the given month,
     * grouped by date — same shape BirthdayService::forMonth() returns,
     * for the calendar grid to consume the same way.
     *
     * @return Collection<string, Collection<int, Rental>>
     */
    public function forMonth(int $year, int $month): Collection
    {
        $first = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $last = $first->endOfMonth()->endOfDay();

        return Rental::query()
            ->with(['space', 'associate'])
            ->where('status', '!=', Rental::STATUS_CANCELADA)
            ->where('starts_at', '<=', $last)
            ->where('ends_at', '>=', $first)
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (Rental $r) => $r->starts_at->toDateString());
    }
}
