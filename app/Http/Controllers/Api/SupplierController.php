<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'suppliers.view');

        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        if (! $isSuperAdmin) {
            abort_unless(
                $user->company_id !== null,
                403,
                'Your account is not assigned to a company.'
            );
        }

        if ($isSuperAdmin) {
            $request->validate([
                'company_id' => [
                    'sometimes',
                    'integer',
                    'exists:companies,id',
                ],
            ]);
        }

        $suppliers = $this->accessibleSuppliers($request)
            ->with('company:id,name')
            ->orderBy('name')
            ->paginate(15);

        return response()->json($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'suppliers.manage');

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
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
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

        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
        ]);

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

        $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('suppliers', 'code')
                    ->where('company_id', $companyId),
            ],
        ]);

        $validated['company_id'] = $companyId;

        $supplier = Supplier::create($validated);

        return response()->json([
            'message' => 'Supplier created successfully.',
            'supplier' => $supplier->load('company:id,name'),
        ], 201);
    }

    public function show(Request $request, int $supplier): JsonResponse
    {
        $this->requirePermission($request, 'suppliers.view');

        $supplier = $this->accessibleSuppliers($request)
            ->with('company:id,name')
            ->whereKey($supplier)
            ->firstOrFail();

        return response()->json([
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, int $supplier): JsonResponse
    {
        $this->requirePermission($request, 'suppliers.manage');

        $supplier = $this->accessibleSuppliers($request)
            ->whereKey($supplier)
            ->firstOrFail();

        if ($request->exists('code')) {
            $request->merge([
                'code' => strtoupper(trim((string) $request->input('code'))),
            ]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('suppliers', 'code')
                    ->where('company_id', $supplier->company_id)
                    ->ignore($supplier->id),
            ],
            'contact_person' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],
            'address' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        $supplier->update($validated);

        return response()->json([
            'message' => 'Supplier updated successfully.',
            'supplier' => $supplier->refresh()
                ->load('company:id,name'),
        ]);
    }

    public function deactivate(
        Request $request,
        int $supplier
    ): JsonResponse {
        $this->requirePermission($request, 'suppliers.manage');

        $supplier = $this->accessibleSuppliers($request)
            ->whereKey($supplier)
            ->firstOrFail();

        $supplier->update([
            'status' => 'inactive',
        ]);

        return response()->json([
            'message' => 'Supplier deactivated successfully.',
            'supplier' => $supplier->refresh()
                ->load('company:id,name'),
        ]);
    }

    private function accessibleSuppliers(Request $request)
    {
        $user = $request->user();

        $query = Supplier::query();

        if ($user->hasRole('Super Admin')) {
            if ($request->filled('company_id')) {
                $query->where('company_id', $request->integer('company_id'));
            }
        } else {
            $query->where('company_id', $user->company_id);
        }

        return $query;
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
