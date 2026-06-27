<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealLender extends Model
{
    public const STATUSES = [
        'inquired' => 'Inquired',
        'term_sheet_received' => 'Term Sheet Received',
        'approved' => 'Approved',
        'funded' => 'Funded',
        'paid_off' => 'Paid Off',
    ];

    protected $fillable = [
        'deal_id',
        'lender_id',
        'lender_loan_program_id',
        'status',
        'notes',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function lender()
    {
        return $this->belongsTo(Lender::class);
    }

    public function loanProgram()
    {
        return $this->belongsTo(LenderLoanProgram::class, 'lender_loan_program_id');
    }
}
