<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelStockMovement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FuelStockMovementController extends Controller
{
    /**
     * Display a paginated list of stock movements.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user->can('inventory.view'),
            403,
            'You are not authorized to view inventory movements.'
        );

        $validated = $request->validate([
            'station_id' => ['nullable', 'integer', 'exists:stations,id'],
            'storage_tank_id' => ['nullable', 'integer', 'exists:storage_tanks,id'],
            'fuel_product_id' => ['nullable', 'integer', 'exists:fuel_products,id'],
            'movement_type' => [
                'nullable',
                'string',
                Rule::in([
                    'opening_stock',
                    'adjustment_in',
                    'adjustment_out',
                    'delivery_in',
                    'transfer_in',
                    'transfer_out',
                    'reversal',
                ]),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = FuelStockMovement::query()
            ->with([
                'station:id,name,code',
                'storageTank:id,name,code',
                'fuelProduct:id,name,code',
                'performedBy:id,name',
                'approvedBy:id,name',
            ])
            ->where('company_id', $user->company_id);

        // Super Admin can view all companies.
        if ($user->hasRole('Super Admin')) {
            $query = FuelStockMovement::query()
                ->with([
                    'station:id,name,code',
                    'storageTank:id,name,code',
                    'fuelProduct:id,name,code',
                    'performedBy:id,name',
                    'approvedBy:id,name',
                ]);
        } else {
            // Restrict users to their assigned stations.
            $stationIds = $user->stations()
                ->pluck('stations.id');

            $query->whereIn('station_id', $stationIds);
        }

        if (!empty($validated['station_id'])) {
            $query->where('station_id', $validated['station_id']);
        }

        if (!empty($validated['storage_tank_id'])) {
            $query->where('storage_tank_id', $validated['storage_tank_id']);
        }

        if (!empty($validated['fuel_product_id'])) {
            $query->where('fuel_product_id', $validated['fuel_product_id']);
        }

        if (!empty($validated['movement_type'])) {
            $query->where('movement_type', $validated['movement_type']);
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $movements = $query
            ->latest('id')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'message' => 'Stock movements retrieved successfully.',
            'data' => $movements,
        ]);
    }

    /**
     * Display a specific stock movement.
     */
    public function show(Request $request, FuelStockMovement $fuelStockMovement)
    {
        $user = $request->user();

        abort_unless(
            $user->can('inventory.view'),
            403,
            'You are not authorized to view inventory movements.'
        );

        if (!$user->hasRole('Super Admin')) {
            abort_unless(
                (int) $fuelStockMovement->company_id === (int) $user->company_id,
                404
            );

            abort_unless(
                $user->stations()
                    ->whereKey($fuelStockMovement->station_id)
                    ->exists(),
                404
            );
        }

        $fuelStockMovement->load([
            'station:id,name,code',
            'storageTank:id,name,code',
            'fuelProduct:id,name,code',
            'performedBy:id,name',
            'approvedBy:id,name',
            'relatedMovement',
        ]);

        return response()->json([
            'message' => 'Stock movement retrieved successfully.',
            'data' => $fuelStockMovement,
        ]);
    }
}