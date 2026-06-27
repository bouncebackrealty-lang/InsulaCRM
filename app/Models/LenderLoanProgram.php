<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LenderLoanProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'lender_id',
        'program_name',
        'interest_rate',
        'points',
        'max_ltc',
        'max_ltv',
        'term_length',
        'purchase_closing_cost_percent',
        'builders_risk_insurance',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:2',
            'points' => 'decimal:2',
            'max_ltc' => 'decimal:2',
            'max_ltv' => 'decimal:2',
            'purchase_closing_cost_percent' => 'decimal:2',
            'builders_risk_insurance' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lender()
    {
        return $this->belongsTo(Lender::class);
    }

    public function dealFundings()
    {
        return $this->hasMany(DealLender::class);
    }
}
