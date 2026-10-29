<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Station;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stations.view');

        $stations = $this->accessibleStations($request)
            ->with('company:id,name')
            ->orderBy('name')
            ->paginate(15);

        return response()->json($stations);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'stations.create');

        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ];

        // Only Super Admin can choose which company owns a station.
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

        $validated['company_id'] = $companyId;

        // Station codes must be unique within a company.
        $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('stations', 'code')
                    ->where('company_id', $companyId),
            ],
        ]);

        $station = DB::transaction(function () use (
            $validated,
            $user,
            $isSuperAdmin
        ) {
            $station = Station::create($validated);

            // A non-admin creator is automatically assigned to the station.
            if (! $isSuperAdmin) {
                $station->users()->syncWithoutDetaching([$user->id]);
            }

            return $station;
        });

        return response()->json([
            'message' => 'Station created successfully.',
            'station' => $station->load('company:id,name'),
        ], 201);
    }

    public function show(Request $request, int $station): JsonResponse
    {
        $this->requirePermission($request, 'stations.view');

        $station = $this->accessibleStations($request)
            ->with('company:id,name')
            ->whereKey($station)
            ->firstOrFail();

        return response()->json(['station' => $station]);
    }

    public function update(
        Request $request,
        int $station
    ): JsonResponse {
        $this->requirePermission($request, 'stations.update');

        $station = $this->accessibleStations($request)
            ->whereKey($station)
            ->firstOrFail();

        // company_id cannot be changed through this endpoint.
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('stations', 'code')
                    ->where('company_id', $station->company_id)
                    ->ignore($station->id),
            ],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        $station->update($validated);

        return response()->json([
            'message' => 'Station updated successfully.',
            'station' => $station->refresh()->load('company:id,name'),
        ]);
    }

    private function accessibleStations(Request $request): Builder
    {
        $user = $request->user();

        if ($user->hasRole('Super Admin')) {
            return Station::query();
        }

        // Non-admin users can only access stations explicitly assigned to them.
        return $user->stations()
            ->getQuery()
            ->where('stations.company_id', $user->company_id);
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