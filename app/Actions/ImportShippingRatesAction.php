<?php

namespace App\Actions;

use App\Models\ShippingRate;
use Illuminate\Support\Facades\DB;

class ImportShippingRatesAction
{
    /**
     * Aplica el snapshot de tarifas fila-por-fila en una única transacción.
     *
     * - Sin tarifa activa → crea.
     * - Con tarifa activa y distinto costo → UPDATE directo de la misma fila.
     * - Con tarifa activa e igual costo → no-op.
     * - Tarifa activa ausente del snapshot → desactiva (nunca elimina).
     *
     * @param  list<array{cp: string, costo_cents: int}>  $rows
     * @return array{created: int, updated: int, unchanged: int, deactivated: int}
     */
    public function execute(array $rows): array
    {
        return DB::transaction(function () use ($rows): array {
            $cps = array_map(fn (array $row): string => $row['cp'], $rows);

            $activas = ShippingRate::query()->whereIn('cp', $cps)->activo()->get()->keyBy('cp');

            $created = 0;
            $updated = 0;
            $unchanged = 0;

            foreach ($rows as $row) {
                $rate = $activas->get($row['cp']);

                if (! $rate instanceof ShippingRate) {
                    ShippingRate::query()->create([
                        'cp' => $row['cp'],
                        'costo_cents' => $row['costo_cents'],
                        'activo' => true,
                    ]);
                    $created++;

                    continue;
                }

                if ($rate->costo_cents !== $row['costo_cents']) {
                    $rate->update(['costo_cents' => $row['costo_cents']]);
                    $updated++;

                    continue;
                }

                $unchanged++;
            }

            $deactivated = ShippingRate::query()->activo()->whereNotIn('cp', $cps)->update([
                'activo' => false,
                'updated_at' => now(),
            ]);

            return [
                'created' => $created,
                'updated' => $updated,
                'unchanged' => $unchanged,
                'deactivated' => $deactivated,
            ];
        });
    }
}
