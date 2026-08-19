<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealContractor extends Model
{
    protected $fillable = [
        'deal_id',
        'contractor_id',
        'quoted_amount',
        'accepted_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quoted_amount' => 'decimal:2',
            'accepted_amount' => 'decimal:2',
        ];
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }
}
