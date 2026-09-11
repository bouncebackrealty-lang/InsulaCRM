<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComparableSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'property_id',
        'address',
        'sale_price',
        'original_list_price',
        'sale_date',
        'days_on_market',
        'sqft',
        'beds',
        'baths',
        'lot_size',
        'year_built',
        'distance_miles',
        'condition',
        'adjustments',
        'adjusted_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'original_list_price' => 'decimal:2',
            'sale_date' => 'date',
            'days_on_market' => 'integer',
            'adjustments' => 'array',
            'adjusted_price' => 'decimal:2',
            'baths' => 'decimal:1',
            'lot_size' => 'decimal:2',
            'distance_miles' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Calculate adjusted price from sale_price + sum of adjustments.
     */
    public function calculateAdjustedPrice(): float
    {
        $adjustments = $this->adjustments ?? [];
        $adjustmentTotal = array_sum(array_values($adjustments));

        return (float) $this->sale_price + $adjustmentTotal;
    }

    /**
     * Calculate the sold price per square foot for display and exports.
     */
    public function getSoldPricePerSqftAttribute(): ?float
    {
        if (! $this->sqft || (float) $this->sqft <= 0) {
            return null;
        }

        return round((float) $this->sale_price / (float) $this->sqft, 2);
    }
}
