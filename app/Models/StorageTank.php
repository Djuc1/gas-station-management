<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'station_id',
    'fuel_product_id',
    'name',
    'code',
    'capacity_litres',
    'status',
])]
class StorageTank extends Model
{
    protected function casts(): array
    {
        return [
            'capacity_litres' => 'decimal:3',
            'current_quantity_litres' => 'decimal:3',
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

    public function fuelProduct(): BelongsTo
    {
        return $this->belongsTo(FuelProduct::class);
    }
}
