<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Station;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'employees.view');

        $employees = $this->accessibleEmployees($request)
            ->with([
                'station:id,name,code',
                'company:id,name',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);

        return response()->json($employees);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'employees.create');

        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        $rules = [
            'station_id' => ['required', 'integer', 'exists:stations,id'],
            'employee_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'employment_date' => ['nullable', 'date'],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];

        // Only Super Admin may choose the employee's company.
        $rules['company_id'] = $isSuperAdmin
            ? ['required', 'integer', 'exists:companies,id']
            : ['prohibited'];

        $validated = $request->validate($rules);

        $station = Station::query()
            ->whereKey($validated['station_id'])
            ->where('status', 'active')
            ->first();

        abort_unless(
            $station,
            422,
            'The selected station does not exist or is inactive.'
        );

        if ($isSuperAdmin) {
            $companyId = (int) $validated['company_id'];

            abort_unless(
                $station->company_id === $companyId,
                422,
                'The selected station does not belong to the selected company.'
            );
        } else {
            abort_unless(
                $user->company_id !== null,
                403,
                'Your account is not assigned to a company.'
            );

            $companyId = (int) $user->company_id;

            abort_unless(
                (int) $station->company_id === $companyId,
                403,
                'The selected station does not belong to your company.'
            );

            abort_unless(
                $user->stations()
                    ->where('stations.id', $station->id)
                    ->exists(),
                403,
                'You are not assigned to the selected station.'
            );
        }

        // Prevent duplicate employee numbers within the company.
        $request->validate([
            'employee_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_number')
                    ->where('company_id', $companyId),
            ],
        ]);

        $validated['company_id'] = $companyId;

        // If a login account is linked, it must belong to this company.
        if (! empty($validated['user_id'])) {
            $linkedUser = \App\Models\User::query()
                ->whereKey($validated['user_id'])
                ->firstOrFail();

            abort_unless(
                (int) $linkedUser->company_id === $companyId,
                422,
                'The linked user must belong to the same company.'
            );

            abort_if(
                Employee::where('user_id', $linkedUser->id)->exists(),
                422,
                'This user is already linked to an employee record.'
            );
        }

        $employee = DB::transaction(function () use ($validated) {
            return Employee::create($validated);
        });

        return response()->json([
            'message' => 'Employee created successfully.',
            'employee' => $employee->load([
                'station:id,name,code',
                'company:id,name',
            ]),
        ], 201);
    }

    public function show(Request $request, int $employee): JsonResponse
    {
        $this->requirePermission($request, 'employees.view');

        $employee = $this->accessibleEmployees($request)
            ->with([
                'station:id,name,code',
                'company:id,name',
            ])
            ->whereKey($employee)
            ->firstOrFail();

        return response()->json(['employee' => $employee]);
    }

    public function update(
        Request $request,
        int $employee
    ): JsonResponse {
        $this->requirePermission($request, 'employees.update');

        $employee = $this->accessibleEmployees($request)
            ->whereKey($employee)
            ->firstOrFail();

        $validated = $request->validate([
            'station_id' => ['sometimes', 'required', 'integer', 'exists:stations,id'],
            'employee_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_number')
                    ->where('company_id', $employee->company_id)
                    ->ignore($employee->id),
            ],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'employment_date' => ['sometimes', 'nullable', 'date'],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        if (isset($validated['station_id'])) {
            $station = Station::query()
                ->whereKey($validated['station_id'])
                ->where('company_id', $employee->company_id)
                ->where('status', 'active')
                ->first();

            abort_unless(
                $station,
                422,
                'The selected station must be active and belong to the employee company.'
            );

            if (! $request->user()->hasRole('Super Admin')) {
                abort_unless(
                    $request->user()->stations()
                        ->where('stations.id', $station->id)
                        ->exists(),
                    403,
                    'You are not assigned to the selected station.'
                );
            }
        }

        $employee->update($validated);

        return response()->json([
            'message' => 'Employee updated successfully.',
            'employee' => $employee->refresh()->load([
                'station:id,name,code',
                'company:id,name',
            ]),
        ]);
    }

    public function deactivate(
        Request $request,
        int $employee
    ): JsonResponse {
        $this->requirePermission($request, 'employees.deactivate');

        $employee = $this->accessibleEmployees($request)
            ->whereKey($employee)
            ->firstOrFail();

        $employee->update(['status' => 'inactive']);

        return response()->json([
            'message' => 'Employee deactivated successfully.',
            'employee' => $employee->refresh(),
        ]);
    }

    private function accessibleEmployees(Request $request): Builder
    {
        $user = $request->user();

        if ($user->hasRole('Super Admin')) {
            return Employee::query();
        }

        return Employee::query()
            ->where('employees.company_id', $user->company_id)
            ->whereIn(
                'employees.station_id',
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
