<?php

namespace App\Services;

use App\Models\FuelStockMovement;
use App\Models\StorageTank;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class FuelStockMovementService
{
    /**
     * Record an authorized opening stock or stock adjustment.
     *
     * Delivery and transfer movements will be handled by their
     * respective workflows to enforce their approval requirements.
     */
    public function recordMovement(
        int $tankId,
        string $movementType,
        string|int $quantity,
        User $performedBy,
        ?string $description = null,
        array $metadata = []
    ): FuelStockMovement {
        $permission = match ($movementType) {
            'opening_stock' => 'inventory.receive',
            'adjustment_in', 'adjustment_out' => 'inventory.adjust',
            default => throw new InvalidArgumentException(
                'Unsupported stock movement type.'
            ),
        };

        if (!$performedBy->can($permission)) {
            abort(403, 'You are not authorized to perform this stock movement.');
        }

        $movementMilliLitres = $this->toMilliLitres($quantity);

        if ($movementMilliLitres <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $tankId,
            $movementType,
            $movementMilliLitres,
            $performedBy,
            $description,
            $metadata
        ) {
            $tank = StorageTank::query()
                ->lockForUpdate()
                ->findOrFail($tankId);

            // Enforce company and station access.
            if (!$performedBy->hasRole('Super Admin')) {
                if (
                    !$performedBy->company_id ||
                    (int) $performedBy->company_id !== (int) $tank->company_id
                ) {
                    abort(403, 'You cannot access this company inventory.');
                }

                $hasStationAccess = $performedBy->stations()
                    ->whereKey($tank->station_id)
                    ->exists();

                if (!$hasStationAccess) {
                    abort(403, 'You are not assigned to this station.');
                }
            }

            // Verify that the tank's ownership relationships are consistent.
            $tank->loadMissing(['company', 'station', 'fuelProduct']);

            if (
                (int) $tank->station->company_id !== (int) $tank->company_id ||
                (int) $tank->fuelProduct->company_id !== (int) $tank->company_id
            ) {
                throw ValidationException::withMessages([
                    'tank' => 'The tank has inconsistent company, station, or fuel product relationships.',
                ]);
            }

            // Reject inactive inventory resources.
            if (
                $tank->status !== 'active' ||
                $tank->station->status !== 'active' ||
                $tank->company->status !== 'active' ||
                $tank->fuelProduct->status !== 'active'
            ) {
                throw ValidationException::withMessages([
                    'tank' => 'The tank, station, company, and fuel product must all be active.',
                ]);
            }

            $quantityBefore = $this->toMilliLitres(
                $tank->current_quantity_litres
            );

            $capacity = $this->toMilliLitres(
                $tank->capacity_litres
            );

            // Opening stock is a one-time initialization.
            if ($movementType === 'opening_stock') {
                $openingStockExists = FuelStockMovement::query()
                    ->where('storage_tank_id', $tank->id)
                    ->where('movement_type', 'opening_stock')
                    ->exists();

                if ($openingStockExists || $quantityBefore !== 0) {
                    throw ValidationException::withMessages([
                        'movement_type' => 'Opening stock has already been initialized for this tank.',
                    ]);
                }
            }

            $increasing = in_array(
                $movementType,
                ['opening_stock', 'adjustment_in'],
                true
            );

            $quantityAfter = $increasing
                ? $quantityBefore + $movementMilliLitres
                : $quantityBefore - $movementMilliLitres;

            if ($quantityAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient stock in the selected tank.',
                ]);
            }

            if ($quantityAfter > $capacity) {
                throw ValidationException::withMessages([
                    'quantity' => 'This movement would exceed the tank capacity.',
                ]);
            }

            // Update the tank balance inside the same transaction.
            $tank->current_quantity_litres = $this->fromMilliLitres(
                $quantityAfter
            );

            $tank->save();

            // Create the permanent stock ledger entry.
            return FuelStockMovement::create([
                'company_id' => $tank->company_id,
                'station_id' => $tank->station_id,
                'storage_tank_id' => $tank->id,
                'fuel_product_id' => $tank->fuel_product_id,
                'movement_type' => $movementType,
                'quantity' => $this->fromMilliLitres(
                    $movementMilliLitres
                ),
                'quantity_before' => $this->fromMilliLitres(
                    $quantityBefore
                ),
                'quantity_after' => $this->fromMilliLitres(
                    $quantityAfter
                ),
                'reference' => 'FSM-' . Str::uuid(),
                'description' => $description,
                'performed_by' => $performedBy->id,
                'approved_by' => null,
                'metadata' => $metadata ?: null,
            ]);
        }, 3);
    }

    /**
     * Convert a decimal litre quantity into integer milli-litres.
     */
    private function toMilliLitres(string|int $quantity): int
    {
        $quantity = (string) $quantity;

        if (!preg_match('/^\d{1,11}(\.\d{1,3})?$/', $quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be a valid positive number with no more than three decimal places.',
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $quantity, 2),
            2,
            ''
        );

        return ((int) $whole * 1000)
            + (int) str_pad($fraction, 3, '0');
    }

    /**
     * Convert integer milli-litres into a three-decimal litre string.
     */
    private function fromMilliLitres(int $quantity): string
    {
        return intdiv($quantity, 1000)
            . '.'
            . str_pad((string) ($quantity % 1000), 3, '0', STR_PAD_LEFT);
    }
}
