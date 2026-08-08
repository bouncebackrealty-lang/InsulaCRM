<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TitleCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'closing_attorney', 'address', 'city', 'state', 'zip_code', 'phone', 'email', 'notes',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function deals()
    {
        return $this->hasMany(Deal::class);
    }

    public function getFullAddressAttribute(): string
    {
        $locality = trim(collect([
            $this->city,
            trim(collect([$this->state, $this->zip_code])->filter()->implode(' ')),
        ])->filter()->implode(', '));

        return collect([$this->address, $locality])->filter()->implode(', ');
    }
}
