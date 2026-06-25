<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contractor extends Model
{
    use HasFactory;

    /**
     * Trade / specialty categories a contractor can be tagged with.
     */
    public const TRADE_CATEGORIES = [
        'roofing' => 'Roofing',
        'electrical' => 'Electrical',
        'plumbing' => 'Plumbing',
        'hvac' => 'HVAC',
        'flooring' => 'Flooring',
        'paint' => 'Paint',
        'drywall' => 'Drywall',
        'framing' => 'Framing',
        'foundation' => 'Foundation',
        'concrete' => 'Concrete',
        'landscaping' => 'Landscaping',
        'general_contractor' => 'General Contractor',
        'other' => 'Other',
    ];

    /**
     * Priority levels.
     */
    public const PRIORITIES = [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ];

    /**
     * Workflow status values.
     */
    public const STATUSES = [
        'contacted' => 'Contacted',
        'bid_submitted' => 'Bid Submitted',
        'hired' => 'Hired',
        'completed' => 'Completed',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'specialty',
        'service_area',
        'priority',
        'referral_source',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'specialty' => 'array',
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

    public function dealBids()
    {
        return $this->hasMany(DealContractor::class);
    }
}
