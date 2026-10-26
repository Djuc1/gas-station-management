<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',

            'companies.view',
            'companies.create',
            'companies.update',

            'stations.view',
            'stations.create',
            'stations.update',

            'employees.view',
            'employees.create',
            'employees.update',
            'employees.deactivate',

            'fuel-products.view',
            'fuel-products.manage',

            'tanks.view',
            'tanks.manage',

            'pumps.view',
            'pumps.manage',

            'fuel-prices.view',
            'fuel-prices.manage',

            'inventory.view',
            'inventory.receive',
            'inventory.adjust',
            'inventory.transfer',

            'deliveries.view',
            'deliveries.create',
            'deliveries.approve',

            'sales.view',
            'sales.create',
            'sales.void',

            'shifts.view',
            'shifts.open',
            'shifts.close',
            'shifts.reconcile',

            'payments.view',
            'payments.record',

            'customers.view',
            'customers.manage',

            'expenses.view',
            'expenses.create',
            'expenses.approve',

            'reports.view',
            'audit-logs.view',

            'users.view',
            'users.manage',
            'roles.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $rolePermissions = [
            'Super Admin' => $permissions,

            'Station Manager' => [
                'dashboard.view',
                'stations.view',
                'stations.create',
                'stations.update',
                'employees.view',
                'employees.create',
                'employees.update',
                'employees.deactivate',
                'fuel-products.view',
                'fuel-products.manage',
                'tanks.view',
                'tanks.manage',
                'pumps.view',
                'pumps.manage',
                'fuel-prices.view',
                'fuel-prices.manage',
                'inventory.view',
                'inventory.receive',
                'inventory.adjust',
                'inventory.transfer',
                'deliveries.view',
                'deliveries.create',
                'deliveries.approve',
                'sales.view',
                'sales.create',
                'sales.void',
                'shifts.view',
                'shifts.open',
                'shifts.close',
                'shifts.reconcile',
                'payments.view',
                'payments.record',
                'customers.view',
                'customers.manage',
                'expenses.view',
                'expenses.create',
                'expenses.approve',
                'reports.view',
                'audit-logs.view',
                'users.view',
            ],

            'Supervisor' => [
                'dashboard.view',
                'employees.view',
                'fuel-products.view',
                'tanks.view',
                'pumps.view',
                'fuel-prices.view',
                'inventory.view',
                'deliveries.view',
                'deliveries.create',
                'sales.view',
                'sales.create',
                'shifts.view',
                'shifts.open',
                'shifts.close',
                'shifts.reconcile',
                'payments.view',
                'payments.record',
                'customers.view',
                'expenses.view',
                'expenses.create',
                'reports.view',
            ],

            'Attendant' => [
                'dashboard.view',
                'fuel-products.view',
                'pumps.view',
                'sales.create',
                'shifts.view',
                'shifts.open',
                'shifts.close',
            ],

            'Accountant' => [
                'dashboard.view',
                'inventory.view',
                'deliveries.view',
                'sales.view',
                'shifts.view',
                'shifts.reconcile',
                'payments.view',
                'payments.record',
                'customers.view',
                'customers.manage',
                'expenses.view',
                'expenses.create',
                'reports.view',
            ],

            'Auditor' => [
                'dashboard.view',
                'stations.view',
                'employees.view',
                'inventory.view',
                'deliveries.view',
                'sales.view',
                'shifts.view',
                'payments.view',
                'customers.view',
                'expenses.view',
                'reports.view',
                'audit-logs.view',
            ],

            'Storekeeper' => [
                'dashboard.view',
                'fuel-products.view',
                'tanks.view',
                'inventory.view',
                'inventory.receive',
                'inventory.transfer',
                'deliveries.view',
                'deliveries.create',
            ],
        ];

        foreach ($rolePermissions as $roleName => $rolePermissionNames) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($rolePermissionNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
