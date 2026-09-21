<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dealer extends Model
{
    protected $fillable = [
        'user_id', 'business_name', 'gst_number', 'kyc_status',
        'credit_limit', 'credit_used', 'price_tier', 'state', 'district', 'pincode',
        'assigned_salesman_id', 'unique_code', 'referred_by_dealer_id',
        'village_street', 'post_office', 'police_station',
        'bank_account_number', 'account_holder_name', 'bank_name', 'ifsc_code',
        'aadhaar_number', 'pan_number', 'aadhaar_document_path', 'pan_document_path',
        'passport_photo_path', 'education_certificate_path', 'bank_passbook_path', 'signed_agreement_path',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'credit_used'  => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(SalesHierarchy::class, 'assigned_salesman_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Dealer::class, 'referred_by_dealer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function availableCredit(): float
    {
        return max(0, (float) $this->credit_limit - (float) $this->credit_used);
    }

    public function canPlaceOrder(float $amount): bool
    {
        return $this->kyc_status === 'verified'
            && ($this->credit_used + $amount) <= $this->credit_limit;
    }
}
