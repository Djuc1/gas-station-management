<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FuelProduct;
use App\Models\FuelStockMovement;
use App\Models\Station;
use App\Models\StorageTank;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FuelStockMovementApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Station $station;
    protected FuelProduct $product;
    protected StorageTank $tank;
    protected User $storekeeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->company = Company::create([
            'name' => 'Test Company',
            'status' => 'active',
        ]);

        $this->station = Station::create([
            'company_id' => $this->company->id,
            'name' => 'Test Station',
            'code' => 'TEST-001',
            'status' => 'active',
        ]);

        $this->product = FuelProduct::create([
            'company_id' => $this->company->id,
            'name' => 'Premium Motor Spirit',
            'code' => 'PMS',
            'unit' => 'litre',
            'status' => 'active',
        ]);

        $this->tank = StorageTank::create([
            'company_id' => $this->company->id,
            'station_id' => $this->station->id,
            'fuel_product_id' => $this->product->id,
            'name' => 'PMS Tank 1',
            'code' => 'PMS-TANK-001',
            'capacity_litres' => 45000,
            'current_quantity_litres' => 1000,
            'status' => 'active',
        ]);

        $this->storekeeper = User::factory()->create();

        $this->storekeeper->forceFill([
            'company_id' => $this->company->id,
        ])->save();

        $this->storekeeper->assignRole('Storekeeper');
        $this->storekeeper->stations()->attach($this->station->id);
    }

    private function createMovement(
        array $overrides = [],
        ?User $performedBy = null
    ): FuelStockMovement {
        static $counter = 0;
        $counter++;

        return FuelStockMovement::create(array_merge([
            'company_id' => $this->company->id,
            'station_id' => $this->station->id,
            'storage_tank_id' => $this->tank->id,
            'fuel_product_id' => $this->product->id,
            'movement_type' => 'opening_stock',
            'quantity' => '1000.000',
            'quantity_before' => '0.000',
            'quantity_after' => '1000.000',
            'reference' => 'TEST-MOVEMENT-' . $counter,
            'description' => 'Feature test movement',
            'performed_by' => ($performedBy ?? $this->storekeeper)->id,
            'approved_by' => null,
            'metadata' => null,
        ], $overrides));
    }

    public function test_unauthenticated_user_cannot_view_stock_movements(): void
    {
        $this->getJson('/api/fuel-stock-movements')
            ->assertUnauthorized();
    }

    public function test_user_without_inventory_view_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'company_id' => $this->company->id,
        ])->save();

        $user->assignRole('Attendant');
        $user->stations()->attach($this->station->id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/fuel-stock-movements')
            ->assertForbidden();
    }

    public function test_authorized_user_can_list_stock_movements(): void
    {
        $this->createMovement();
        $this->createMovement([
            'movement_type' => 'adjustment_in',
            'reference' => 'TEST-ADJUSTMENT-001',
        ]);

        $response = $this->actingAs($this->storekeeper, 'sanctum')
            ->getJson('/api/fuel-stock-movements');

        $response->assertOk()
            ->assertJsonPath('message', 'Stock movements retrieved successfully.')
            ->assertJsonPath('data.total', 2);
    }

    public function test_user_can_filter_movements_by_type(): void
    {
        $this->createMovement();

        $this->createMovement([
            'movement_type' => 'adjustment_in',
            'reference' => 'TEST-ADJUSTMENT-002',
        ]);

        $response = $this->actingAs($this->storekeeper, 'sanctum')
            ->getJson('/api/fuel-stock-movements?movement_type=adjustment_in');

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.movement_type', 'adjustment_in');
    }

    public function test_user_can_filter_movements_by_station_tank_and_product(): void
    {
        $movement = $this->createMovement();

        $response = $this->actingAs($this->storekeeper, 'sanctum')
            ->getJson(
                '/api/fuel-stock-movements'
                . '?station_id=' . $this->station->id
                . '&storage_tank_id=' . $this->tank->id
                . '&fuel_product_id=' . $this->product->id
            );

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $movement->id);
    }

    public function test_user_can_filter_movements_by_date(): void
    {
        $oldMovement = $this->createMovement([
            'reference' => 'TEST-OLD-001',
        ]);

        $oldMovement->forceFill([
            'created_at' => now()->subDays(5),
        ])->saveQuietly();

        $recentMovement = $this->createMovement([
            'reference' => 'TEST-RECENT-001',
        ]);

        $response = $this->actingAs($this->storekeeper, 'sanctum')
            ->getJson(
                '/api/fuel-stock-movements?date_from='
                . now()->subDay()->toDateString()
            );

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $recentMovement->id);
    }

    public function test_pagination_respects_per_page_parameter(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createMovement([
                'reference' => 'TEST-PAGINATION-' . $i,
            ]);
        }

        $response = $this->actingAs($this->storekeeper, 'sanctum')
            ->getJson('/api/fuel-stock-movements?per_page=2');

        $response->assertOk()
            ->assertJsonPath('data.per_page', 2)
            ->assertJsonPath('data.total', 5)
            ->assertJsonCount(2, 'data.data');
    }

    public function test_user_can_view_a_single_stock_movement(): void
    {
        $movement = $this->createMovement();

        $this->actingAs($this->storekeeper, 'sanctum')
            ->getJson('/api/fuel-stock-movements/' . $movement->id)
            ->assertOk()
            ->assertJsonPath('data.id', $movement->id)
            ->assertJsonPath('data.reference', $movement->reference);
    }

    public function test_user_cannot_view_movement_from_unassigned_station(): void
    {
        $otherStation = Station::create([
            'company_id' => $this->company->id,
            'name' => 'Other Station',
            'code' => 'TEST-002',
            'status' => 'active',
        ]);

        $movement = $this->createMovement([
            'station_id' => $otherStation->id,
        ]);

        $this->actingAs($this->storekeeper, 'sanctum')
            ->getJson('/api/fuel-stock-movements/' . $movement->id)
            ->assertNotFound();
    }

    public function test_user_cannot_view_movement_from_another_company(): void
    {
        $otherCompany = Company::create([
            'name' => 'Other Company',
            'status' => 'active',
        ]);

        $otherStation = Station::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Station',
            'code' => 'OTHER-001',
            'status' => 'active',
        ]);

        $otherProduct = FuelProduct::create([
            'company_id' => $otherCompany->id,
            'name' => 'Automotive Gas Oil',
            'code' => 'AGO',
            'unit' => 'litre',
            'status' => 'active',
        ]);

        $otherTank = StorageTank::create([
            'company_id' => $otherCompany->id,
            'station_id' => $otherStation->id,
            'fuel_product_id' => $otherProduct->id,
            'name' => 'AGO Tank',
            'code' => 'AGO-TANK-001',
            'capacity_litres' => 30000,
            'current_quantity_litres' => 500,
            'status' => 'active',
        ]);

        $movement = $this->createMovement([
            'company_id' => $otherCompany->id,
            'station_id' => $otherStation->id,
            'storage_tank_id' => $otherTank->id,
            'fuel_product_id' => $otherProduct->id,
        ]);

        $this->actingAs($this->storekeeper, 'sanctum')
            ->getJson('/api/fuel-stock-movements/' . $movement->id)
            ->assertNotFound();
    }

    public function test_invalid_pagination_value_is_rejected(): void
    {
        $this->actingAs($this->storekeeper, 'sanctum')
            ->getJson('/api/fuel-stock-movements?per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_invalid_movement_type_filter_is_rejected(): void
    {
        $this->actingAs($this->storekeeper, 'sanctum')
            ->getJson('/api/fuel-stock-movements?movement_type=invalid_type')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('movement_type');
    }
}
