<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetiTransfer extends Model
{
    protected $fillable = [
        'transfer_number', 'type',
        'from_location', 'to_location', 'to_dealer_id',
        'items', 'total_units', 'total_value',
        'status', 'notes',
        'created_by', 'approved_by', 'transferred_at',
    ];

    protected $casts = [
        'items'          => 'array',
        'transferred_at' => 'datetime',
    ];

    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function toDealer()   { return $this->belongsTo(Dealer::class, 'to_dealer_id'); }

    public static function generateTransferNumber(): string
    {
        $last = self::orderByDesc('id')->first();
        $next = $last ? ((int) substr($last->transfer_number, 4)) + 1 : 1;
        return 'PTR-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
