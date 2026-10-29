<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FuelProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class FuelProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'fuel-products.view');

        $user = $request->user();

        $products = FuelProduct::query()
            ->with('company:id,name')
            ->when(
                ! $user->hasRole('Super Admin'),
                fn ($query) => $query->where(
                    'company_id',
                    $user->company_id
                )
            )
            ->orderBy('name')
            ->paginate(15);

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'fuel-products.manage');

        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        if (! $isSuperAdmin) {
            abort_unless(
                $user->company_id !== null,
                403,
                'Your account is not assigned to a company.'
            );
        }

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'unit' => ['sometimes', 'required', 'string', 'max:20'],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ];

        if ($isSuperAdmin) {
            $rules['company_id'] = [
                'required',
                'integer',
                'exists:companies,id',
            ];
        } else {
            $rules['company_id'] = ['prohibited'];
        }

        $validated = $request->validate($rules);

        $companyId = $isSuperAdmin
            ? (int) $validated['company_id']
            : (int) $user->company_id;

        $company = Company::query()
            ->whereKey($companyId)
            ->where('status', 'active')
            ->first();

        abort_unless(
            $company,
            422,
            'The selected company does not exist or is inactive.'
        );

        $validated['code'] = strtoupper(trim($validated['code']));

        $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('fuel_products', 'code')
                    ->where('company_id', $companyId),
            ],
        ]);

        $validated['company_id'] = $companyId;

        $product = FuelProduct::create($validated);

        return response()->json([
            'message' => 'Fuel product created successfully.',
            'fuel_product' => $product->load('company:id,name'),
        ], 201);
    }

    public function show(Request $request, int $fuelProduct): JsonResponse 
    {
        $this->requirePermission($request, 'fuel-products.view');

        $product = $this->accessibleProducts($request)
            ->with('company:id,name')
            ->whereKey($fuelProduct)
            ->firstOrFail();

        return response()->json([
            'fuel_product' => $product,
        ]);
    }

    public function update(Request $request, int $fuelProduct): JsonResponse 
    {
        $this->requirePermission($request, 'fuel-products.manage');

        $product = $this->accessibleProducts($request)
            ->whereKey($fuelProduct)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('fuel_products', 'code')
                    ->where('company_id', $product->company_id)
                    ->ignore($product->id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'unit' => ['sometimes', 'required', 'string', 'max:20'],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $product->update($validated);

        return response()->json([
            'message' => 'Fuel product updated successfully.',
            'fuel_product' => $product->refresh()->load('company:id,name'),
        ]);
    }

    private function accessibleProducts(Request $request)
    {
        $user = $request->user();

        $query = FuelProduct::query();

        if (! $user->hasRole('Super Admin')) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    private function requirePermission(Request $request, string $permission): void 
    {
        abort_unless(
            $request->user()?->can($permission),
            403,
            'You do not have permission to perform this action.'
        );
    }
}
