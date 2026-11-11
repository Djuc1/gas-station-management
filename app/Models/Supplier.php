<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'contact_person',
        'email',
        'phone',
        'address',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fuelDeliveries(): HasMany
    {
        return $this->hasMany(FuelDelivery::class);
    }
}