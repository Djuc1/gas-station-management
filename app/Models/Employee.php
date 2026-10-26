<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'station_id',
    'user_id',
    'employee_number',
    'first_name',
    'last_name',
    'phone',
    'email',
    'job_title',
    'employment_date',
    'status',
])]
class Employee extends Model
{
    protected function casts(): array
    {
        return [
            'employment_date' => 'date',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}