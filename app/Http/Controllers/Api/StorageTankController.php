<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FuelProduct;
use App\Models\Station;
use App\Models\StorageTank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StorageTankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'tanks.view');

        $tanks = $this->accessibleTanks($request)
            ->with([
                'company:id,name',
                'station:id,name,code',
                'fuelProduct:id,name,code,unit',
            ])
            ->orderBy('name')
            ->paginate(15);

        return response()->json($tanks);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'tanks.manage');

        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        $rules = [
            'station_id' => ['required', 'integer', 'exists:stations,id'],
            'fuel_product_id' => [
                'required',
                'integer',
                'exists:fuel_products,id',
            ],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30'],
            'capacity_litres' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,3',
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
            'current_quantity_litres' => ['prohibited'],
        ];

        // Only Super Admin can choose the company.
        $rules['company_id'] = $isSuperAdmin
            ? ['required', 'integer', 'exists:companies,id']
            : ['prohibited'];

        $validated = $request->validate($rules);

        if ($isSuperAdmin) {
            $companyId = (int) $validated['company_id'];
        } else {
            abort_unless(
                $user->company_id !== null,
                403,
                'Your account is not assigned to a company.'
            );

            $companyId = (int) $user->company_id;
        }

        $company = Company::query()
            ->whereKey($companyId)
            ->where('status', 'active')
            ->first();

        abort_unless(
            $company,
            422,
            'The selected company does not exist or is inactive.'
        );

        // Non-admin users can only use stations assigned to them.
        $stationQuery = Station::query()
            ->whereKey($validated['station_id'])
            ->where('company_id', $companyId)
            ->where('status', 'active');

        if (! $isSuperAdmin) {
            $stationQuery->whereIn(
                'stations.id',
                $user->stations()->select('stations.id')
            );
        }

        $station = $stationQuery->first();

        abort_unless(
            $station,
            422,
            'The selected station is inactive, inaccessible, or does not belong to this company.'
        );

        $fuelProduct = FuelProduct::query()
            ->whereKey($validated['fuel_product_id'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->first();

        abort_unless(
            $fuelProduct,
            422,
            'The selected fuel product is inactive or does not belong to this company.'
        );

        $validated['code'] = strtoupper(trim($validated['code']));

        $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('storage_tanks', 'code')
                    ->where('station_id', $station->id),
            ],
        ]);

        $tank = StorageTank::create([
            'company_id' => $companyId,
            'station_id' => $station->id,
            'fuel_product_id' => $fuelProduct->id,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'capacity_litres' => $validated['capacity_litres'],
            'status' => $validated['status'] ?? 'active',
            // Initial stock is always zero.
        ]);

        return response()->json([
            'message' => 'Storage tank created successfully.',
            'storage_tank' => $tank->load([
                'company:id,name',
                'station:id,name,code',
                'fuelProduct:id,name,code,unit',
            ]),
        ], 201);
    }

    public function show(Request $request, int $storageTank): JsonResponse
    {
        $this->requirePermission($request, 'tanks.view');

        $tank = $this->accessibleTanks($request)
            ->with([
                'company:id,name',
                'station:id,name,code',
                'fuelProduct:id,name,code,unit',
            ])
            ->whereKey($storageTank)
            ->firstOrFail();

        return response()->json([
            'storage_tank' => $tank,
        ]);
    }

    public function update(Request $request, int $storageTank): JsonResponse 
    {
        $this->requirePermission($request, 'tanks.manage');

        $tank = $this->accessibleTanks($request)
            ->whereKey($storageTank)
            ->firstOrFail();

        // Ownership, station, fuel product and stock cannot be changed here.
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('storage_tanks', 'code')
                    ->where('station_id', $tank->station_id)
                    ->ignore($tank->id),
            ],
            'capacity_litres' => [
                'sometimes',
                'required',
                'numeric',
                'gt:0',
                'decimal:0,3',
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
            'company_id' => ['prohibited'],
            'station_id' => ['prohibited'],
            'fuel_product_id' => ['prohibited'],
            'current_quantity_litres' => ['prohibited'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        if (isset($validated['capacity_litres'])) {
            abort_if(
                (float) $validated['capacity_litres']
                    < (float) $tank->current_quantity_litres,
                422,
                'Tank capacity cannot be lower than its current stock.'
            );
        }

        $tank->update($validated);

        return response()->json([
            'message' => 'Storage tank updated successfully.',
            'storage_tank' => $tank->refresh()->load([
                'company:id,name',
                'station:id,name,code',
                'fuelProduct:id,name,code,unit',
            ]),
        ]);
    }

    private function accessibleTanks(Request $request): Builder
    {
        $user = $request->user();

        if ($user->hasRole('Super Admin')) {
            return StorageTank::query();
        }

        return StorageTank::query()
            ->where('company_id', $user->company_id)
            ->whereIn(
                'station_id',
                $user->stations()->select('stations.id')
            );
    }

    private function requirePermission(
        Request $request,
        string $permission
    ): void {
        abort_unless(
            $request->user()?->can($permission),
            403,
            'You do not have permission to perform this action.'
        );
    }
}
