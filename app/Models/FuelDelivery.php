<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelDelivery extends Model
{
    protected $fillable = [
        'company_id',
        'station_id',
        'supplier_id',
        'storage_tank_id',
        'fuel_product_id',
        'reference',
        'invoice_number',
        'quantity_litres',
        'unit_price',
        'total_cost',
        'delivered_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_litres' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'delivered_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function storageTank(): BelongsTo
    {
        return $this->belongsTo(StorageTank::class);
    }

    public function fuelProduct(): BelongsTo
    {
        return $this->belongsTo(FuelProduct::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}