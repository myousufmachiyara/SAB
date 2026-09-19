<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_no',
        'date',
        'remarks',
        'account_id',
        'type',
        'discount',
        'net_amount',
        'created_by',
    ];

    public function items()
    {
        return $this->hasMany(SaleInvoiceItem::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'account_id');
    }

    // reference is stored as 'SI-{id}' — cannot use standard hasMany
    // Use getReceiptVouchersAttribute or load manually in controller
    public function receiptVouchers()
    {
        return $this->hasMany(Voucher::class, 'reference', 'id')
                    ->where('voucher_type', 'receipt');
    }

    // Accessor so $invoice->si_reference always gives 'SI-{id}'
    public function getSiReferenceAttribute(): string
    {
        return 'SI-' . $this->id;
    }
}