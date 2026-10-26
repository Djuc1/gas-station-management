<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'companies.view');

        return response()->json(
            Company::query()
                ->orderBy('name')
                ->paginate(15)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'companies.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => [
                'nullable',
                'string',
                'max:255',
                'unique:companies,registration_number',
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        $company = Company::create($validated);

        return response()->json([
            'message' => 'Company created successfully.',
            'company' => $company,
        ], 201);
    }

    public function show(Request $request, Company $company): JsonResponse
    {
        $this->requirePermission($request, 'companies.view');

        return response()->json([
            'company' => $company,
        ]);
    }

    public function update(
        Request $request,
        Company $company
    ): JsonResponse {
        $this->requirePermission($request, 'companies.update');

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'registration_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('companies', 'registration_number')
                    ->ignore($company->id),
            ],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        $company->update($validated);

        return response()->json([
            'message' => 'Company updated successfully.',
            'company' => $company->refresh(),
        ]);
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
