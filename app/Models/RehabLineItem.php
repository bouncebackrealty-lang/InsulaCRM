<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RehabLineItem extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'floors' => 'Floors',
        'kitchen' => 'Kitchen',
        'plumbing_and_baths' => 'Plumbing and Baths',
        'hardware_and_fixtures' => 'Hardware and Fixtures',
        'electrical' => 'Electrical',
        'interior_paint_and_drywall' => 'Interior Paint and Drywall',
        'doors_framing_and_windows' => 'Doors Framing and Windows',
        'exterior' => 'Exterior',
        'foundation_and_framing' => 'Foundation and Framing',
        'hvac' => 'HVAC',
        'general_conditions' => 'General Conditions',
        'optional_other' => 'Optional/Other',
    ];

    public const STATUSES = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'complete' => 'Complete',
    ];

    protected $fillable = [
        'tenant_id',
        'deal_id',
        'contractor_id',
        'line_item',
        'category',
        'budgeted_cost',
        'estimated_duration_days',
        'amount_paid',
        'status',
    ];

    protected $appends = [
        'remaining_balance',
    ];

    protected function casts(): array
    {
        return [
            'budgeted_cost' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'estimated_duration_days' => 'integer',
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

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function getRemainingBalanceAttribute(): float
    {
        return (float) $this->budgeted_cost - (float) $this->amount_paid;
    }
}
