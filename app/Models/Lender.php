<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lender extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'company',
        'phone',
        'email',
        'service_area',
        'notes',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function loanPrograms()
    {
        return $this->hasMany(LenderLoanProgram::class);
    }

    public function dealFundings()
    {
        return $this->hasMany(DealLender::class);
    }
}
