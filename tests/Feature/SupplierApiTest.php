<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\FuelDelivery;
use App\Models\FuelProduct;
use App\Models\Station;
use App\Models\StorageTank;
use Illuminate\Database\QueryException;

class SupplierApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $storekeeper;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->company = Company::create([
            'name' => 'Test Company',
            'status' => 'active',
        ]);

        $this->storekeeper = $this->createCompanyUser('Storekeeper');
        $this->manager = $this->createCompanyUser('Station Manager');
    }

    private function createCompanyUser(string $role): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'company_id' => $this->company->id,
        ])->save();

        $user->assignRole($role);

        return $user;
    }

    private function createSupplier(array $overrides = []): Supplier
    {
        static $counter = 0;
        $counter++;

        return Supplier::create(array_merge([
            'company_id' => $this->company->id,
            'name' => 'Test Supplier ' . $counter,
            'code' => 'SUP-' . $counter,
            'contact_person' => 'John Doe',
            'email' => 'supplier' . $counter . '@example.com',
            'phone' => '08012345678',
            'address' => 'Test Address',
            'status' => 'active',
        ], $overrides));
    }

    public function test_unauthenticated_user_cannot_view_suppliers(): void
    {
        $this->getJson('/api/suppliers')
            ->assertUnauthorized();
    }

    public function test_user_without_supplier_view_permission_is_forbidden(): void
    {
        $attendant = $this->createCompanyUser('Attendant');

        $this->actingAs($attendant, 'sanctum')
            ->getJson('/api/suppliers')
            ->assertForbidden();
    }

    public function test_authorized_user_can_list_company_suppliers(): void
    {
        $this->createSupplier();

        $this->createSupplier();

        $otherCompany = Company::create([
            'name' => 'Other Company',
            'status' => 'active',
        ]);

        Supplier::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Supplier',
            'code' => 'OTHER-001',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->storekeeper, 'sanctum')
            ->getJson('/api/suppliers');

        $response->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_without_supplier_manage_permission_cannot_create_supplier(): void
    {
        $this->actingAs($this->storekeeper, 'sanctum')
            ->postJson('/api/suppliers', [
                'name' => 'New Supplier',
                'code' => 'NEW-001',
            ])
            ->assertForbidden();
    }

    public function test_manager_can_create_supplier(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/suppliers', [
                'name' => 'New Supplier',
                'code' => ' new-001 ',
                'contact_person' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '08012345678',
                'address' => 'Lagos',
            ]);

        $response->assertCreated()
            ->assertJsonPath('supplier.name', 'New Supplier')
            ->assertJsonPath('supplier.code', 'NEW-001')
            ->assertJsonPath('supplier.company_id', $this->company->id);

        $this->assertDatabaseHas('suppliers', [
            'company_id' => $this->company->id,
            'code' => 'NEW-001',
        ]);
    }

    public function test_regular_user_cannot_assign_supplier_to_another_company(): void
    {
        $otherCompany = Company::create([
            'name' => 'Other Company',
            'status' => 'active',
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/suppliers', [
                'name' => 'Unauthorized Supplier',
                'code' => 'UNAUTH-001',
                'company_id' => $otherCompany->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');

        $this->assertDatabaseMissing('suppliers', [
            'code' => 'UNAUTH-001',
        ]);
    }

    public function test_duplicate_supplier_code_is_rejected_within_company(): void
    {
        $this->createSupplier([
            'code' => 'DUP-001',
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/suppliers', [
                'name' => 'Duplicate Supplier',
                'code' => 'DUP-001',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_manager_can_update_supplier(): void
    {
        $supplier = $this->createSupplier();

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson('/api/suppliers/' . $supplier->id, [
                'name' => 'Updated Supplier',
                'code' => 'UPDATED-001',
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('supplier.name', 'Updated Supplier')
            ->assertJsonPath('supplier.code', 'UPDATED-001')
            ->assertJsonPath('supplier.status', 'inactive');

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Supplier',
            'status' => 'inactive',
        ]);
    }

    public function test_user_cannot_view_supplier_from_another_company(): void
    {
        $otherCompany = Company::create([
            'name' => 'Other Company',
            'status' => 'active',
        ]);

        $supplier = $this->createSupplier([
            'company_id' => $otherCompany->id,
            'code' => 'OTHER-002',
        ]);

        $this->actingAs($this->storekeeper, 'sanctum')
            ->getJson('/api/suppliers/' . $supplier->id)
            ->assertNotFound();
    }

    public function test_manager_can_deactivate_supplier_without_deleting_it(): void
    {
        $supplier = $this->createSupplier();

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson('/api/suppliers/' . $supplier->id . '/deactivate')
            ->assertOk()
            ->assertJsonPath('supplier.status', 'inactive');

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'status' => 'inactive',
        ]);
    }

    public function test_super_admin_can_list_suppliers_across_companies(): void
    {
        $otherCompany = Company::create([
            'name' => 'Other Company',
            'status' => 'active',
        ]);

        $this->createSupplier();

        Supplier::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Supplier',
            'code' => 'OTHER-003',
            'status' => 'active',
        ]);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/suppliers')
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_super_admin_can_create_supplier_for_selected_company(): void
    {
        $otherCompany = Company::create([
            'name' => 'Other Company',
            'status' => 'active',
        ]);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/suppliers', [
                'company_id' => $otherCompany->id,
                'name' => 'Super Admin Supplier',
                'code' => 'ADMIN-001',
            ])
            ->assertCreated()
            ->assertJsonPath('supplier.company_id', $otherCompany->id);

        $this->assertDatabaseHas('suppliers', [
            'company_id' => $otherCompany->id,
            'code' => 'ADMIN-001',
        ]);
    }


    public function test_supplier_with_fuel_deliveries_cannot_be_deleted(): void
    {
        $supplier = $this->createSupplier();

        $station = Station::create([
            'company_id' => $this->company->id,
            'name' => 'Test Station',
            'code' => 'SUP-TEST-001',
            'status' => 'active',
        ]);

        $product = FuelProduct::create([
            'company_id' => $this->company->id,
            'name' => 'Premium Motor Spirit',
            'code' => 'SUP-PMS',
            'unit' => 'litre',
            'status' => 'active',
        ]);

        $tank = StorageTank::create([
            'company_id' => $this->company->id,
            'station_id' => $station->id,
            'fuel_product_id' => $product->id,
            'name' => 'Test PMS Tank',
            'code' => 'SUP-TANK-001',
            'capacity_litres' => 45000,
            'current_quantity_litres' => 1000,
            'status' => 'active',
        ]);

        FuelDelivery::forceCreate([
            'company_id' => $this->company->id,
            'station_id' => $station->id,
            'supplier_id' => $supplier->id,
            'storage_tank_id' => $tank->id,
            'fuel_product_id' => $product->id,
            'reference' => 'SUP-DELIVERY-001',
            'invoice_number' => 'SUP-INVOICE-001',
            'quantity_litres' => '5000.000',
            'unit_price' => '900.00',
            'total_cost' => '4500000.00',
            'delivered_at' => now(),
            'status' => 'pending',
            'created_by' => $this->manager->id,
        ]);

        try {
            $supplier->delete();

            $this->fail(
                'A supplier with historical fuel deliveries must not be deleted.'
            );
        } catch (QueryException $exception) {
            $this->assertDatabaseHas('suppliers', [
                'id' => $supplier->id,
            ]);
        }
    }

}
